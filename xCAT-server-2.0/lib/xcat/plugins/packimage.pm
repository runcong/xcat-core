package xCAT_plugin::packimage;
use xCAT::Table;
use Getopt::Long;
use File::Path;
use File::Copy;
use Cwd;
use File::Temp;
Getopt::Long::Configure("bundling");
Getopt::Long::Configure("pass_through");

sub handled_commands {
     return {
            packimage => "packimage",
   }
}

sub process_request {
   my $sitetab = xCAT::Table->new('site');
   my $request = shift;
   my $callback = shift;
   my $doreq = shift;
   my $ent = $sitetab->getAttribs({key=>'installdir'},['value']);
   my $installroot = "/install";

   if ($ent and $ent->{value}) {
      $installroot = $ent->{value};
   }
   @ARGV = @{$request->{arg}};
    my $osver;
    my $arch;
    my $profile;
    my $method='cpio';
   GetOptions(
      "profile|p=s" => \$profile,
      "arch|a=s" => \$arch,
      "osver|o=s" => \$osver,
      "method|m=s" => \$method,
      "help|h" => \$help,
      "version|v" => \$version
      );
   if ($version) {
      $callback->({info=>["Version 2.0"]});
      return;
   }
   if ($help) {
      $callback->({info=>["packimage -h \npackimage -v \npackimage [-p profile] [-a architecture] [-o OS] [-m method]\n"]});
      return;
   }
   my $distname = $osver;
   until (-r  "$::XCATROOT/share/xcat/netboot/$distname/" or not $distname) {
      chop($distname);
   }
   unless ($distname) {
      $callback->({error=>["Unable to find $::XCATROOT/share/xcat/netboot directory for $osver"],errorcode=>[1]});
      return;
   }
    unless ($installroot) {
        $callback->({error=>["No installdir defined in site table"],errorcode=>[1]});
        return;
    }
    my $oldpath=cwd();
    my $exlistloc;
    if (-r "$::XCATROOT/share/xcat/netboot/$distname/$profile.$osver.$arch.exlist") {
       $exlistloc = "$::XCATROOT/share/xcat/netboot/$distname/$profile.$osver.$arch.exlist";
    } elsif (-r "$::XCATROOT/share/xcat/netboot/$distname/$profile.$arch.exlist") {
       $exlistloc = "$::XCATROOT/share/xcat/netboot/$distname/$profile.$arch.exlist";
    } elsif (-r "$::XCATROOT/share/xcat/netboot/$distname/$profile.$osver.exlist") {
       $exlistloc = "$::XCATROOT/share/xcat/netboot/$distname/$profile.$osver.exlist";
    } elsif (-r "$::XCATROOT/share/xcat/netboot/$distname/$profile.exlist") {
       $exlistloc = "$::XCATROOT/share/xcat/netboot/$distname/$profile.exlist";
    } else {
       $callback->({error=>["Unable to finde file exclusion list under $::XCATROOT/share/xcat/netboot/$distname/ for $profile/$arch/$osver"],errorcode=>[1]});
       next;
    }
    my $exlist;
    open($exlist,"<",$exlistloc);
    my $excludestr = "find . ";
    while (<$exlist>) {
       chomp $_;
       $excludestr .= "'!' -wholename '".$_."' -a ";
    }
    close($exlist);

	# add the xCAT post scripts to the image
	copybootscript($installroot, $osver, $arch, $profile, $callback);

    my $verb = "Packing";
    if ($method =~ /nfs/) {
      $verb = "Preping";
    }
    $callback->({data=>["$verb contents of $installroot/netboot/$osver/$arch/$profile/rootimg"]});
    if ($method =~ /nfs/) {
      $callback->({data=>["\nNOTE: Contents of $installroot/netboot/$osver/$arch/$profile/rootimg\nMUST be available on all service and management nodes and NFS exported."]});
    }
    my $temppath;
    if ($method =~ /cpio/) {
       $excludestr =~ s!-a \z!|cpio -H newc -o | gzip -c - > ../rootimg.gz!;
    } elsif ($method =~ /squashfs/) {
      $temppath = mkdtemp("/tmp/packimage.$$.XXXXXXXX");
      $excludestr =~ s!-a \z!|cpio -dump $temppath!; 
    } elsif ($method =~ /nfs/) {
       $excludestr = "touch ../rootimg.nfs";
    } else {
       $callback->({error=>["Invalid method '$method' requested"],errorcode=>[1]});
    }

    if (! -d "$installroot/netboot/$osver/$arch/$profile/rootimg") {
       $callback->({error=>["$installroot/netboot/$osver/$arch/$profile/rootimg does not exist, run genimage -o $osver -p $profile on a server with matching architecture"]});
       return;
    }
    chdir("$installroot/netboot/$osver/$arch/$profile/rootimg");
    system($excludestr);
    if ($method =~ /squashfs/) {
       my $flags;
       if ($arch =~ /x86/) {
          $flags="-le";
       } elsif ($arch =~ /ppc/) {
          $flags="-be";
       }
       if (! -x "/sbin/mksquashfs") {
          $callback->({error=>["mksquashfs not found, squashfs-tools rpm should be installed on the management node"],errorcode=>[1]});
          return;
       }
       my $rc = system("mksquashfs $temppath ../rootimg.sfs $flags");
       if ($rc) {
          $callback->({error=>["mksquashfs could not be run successfully"],errorcode=>[1]});
          return;
       }
       chmod(0644,"../rootimg.sfs");
    }
    chdir($oldpath);
}

###########################################################
#
#  copybootscript - copy the xCAT diskless init scripts to the image
#
#############################################################
sub copybootscript {

    my $installroot  = shift;
    my $osver  = shift;
    my $arch = shift;
    my $profile = shift;
    my $callback = shift;

    if ( -f "$installroot/postscripts/xcatdsklspost") {

        # copy the xCAT diskless post script to the image
        mkpath("$installroot/netboot/$osver/$arch/$profile/rootimg/opt/xcat");  

        copy ("$installroot/postscripts/xcatdsklspost", "$installroot/netboot/$osver/$arch/$profile/rootimg/opt/xcat/xcatdsklspost");

        chmod(0755,"$installroot/netboot/$osver/$arch/$profile/rootimg/opt/xcat/xcatdsklspost");

    } else {

	my $rsp;
        push @{$rsp->{data}}, "Could not find the script $installroot/postscripts/xcatdsklspost.\n";
        xCAT::MsgUtils->message("E", $rsp, $callback);
        return 1;
    }

	if ( -f "$installroot/postscripts/xcatpostinit") {

        # copy the linux diskless init script to the image
        #   - & set the permissions
        copy ("$installroot/postscripts/xcatpostinit","$installroot/netboot/$osver/$arch/$profile/rootimg/etc/init.d/xcatpostinit");

        chmod(0755,"$installroot/netboot/$osver/$arch/$profile/rootimg/etc/init.d/xcatpostinit");

        # run chkconfig
        my $chkcmd = "chroot $installroot/netboot/$osver/$arch/$profile/rootimg chkconfig --add xcatpostinit";

        my $rc = system($chkcmd);
        if ($rc) {
		my $rsp;
        	push @{$rsp->{data}}, "Could not run the chkconfig command.\n";
        	xCAT::MsgUtils->message("E", $rsp, $callback);
            	return 1;
        }
    } else {
	my $rsp;
        push @{$rsp->{data}}, "Could not find the script $installroot/postscripts/xcatpostinit.\n";
        xCAT::MsgUtils->message("E", $rsp, $callback);
        return 1;
    }
	return 0;
}

