#!/usr/bin/perl
####################################################################################
# Automate copying required config files, key.php and attachments directory when
# doing a Kayako version upgrade.
# 8/29/2014
# Version 1.5
# jchieppa@gmail.com
####################################################################################

use strict;
use warnings;
use autodie;
use POSIX 'strftime';

# SET YOUR DATABASE VARIABLES
my $dbuser = 'username';
my $dbpass = 'password';
my $dbname = 'kayako';

# SET YOUR HTDOCS ROOT 
my $path = "/www/htdocs";

# SET YOUR HELPDESK URL
my $hdurl = "http://helpdesk.domain.com";

# SET APACHE USER & GROUP
my $apache = "nobody.www";

# MUST BE RUN AS ROOT
my $login = (getpwuid $>);
die "Must run as root.  Exiting!\n" if $login ne 'root';

# FUNCTION TO TRIM WHITESPACE
sub trim($)
{
    my $string = shift;
    $string =~ s/^\s+//;
    $string =~ s/\s+$//;
    return $string;
}

# SETUP DATE/TIMESTAMP VARIABLE USED LATER IN DB FILENAMING.
my $date = strftime '%Y-%m-%d-%H', localtime;
my $dbbackup = "mysqldump -u $dbuser -p$dbpass $dbname | gzip -c | cat > $path/backup/Kayako-$date.sql.gz";

# BACKUP EXISTING KAYAKO DATABASE 
if (-e "$path/backup/Kayako-$date.sql.gz") {
	print "Database backup has already occured within the last hour. Continuing on.\n";
	} else {
	print "No Database backup within the last hour exists.  Backing up now, this may take some time.\n";
	system ("$dbbackup");
}

# GET KAYAKO EXISTING SOURCE FOLDER.
print 'What is the existing Kayako source folder name: ';
	chomp(my $oldsource = <STDIN>);
	$oldsource = trim($oldsource);
	die "Existing Source Folder does not exist!\n"
	unless -d ($oldsource);

# GET KAYAKO NEW SOURCE FOLDER.
print 'What is the new Kayako source folder name: ';
	chomp(my $newsource = <STDIN>);
	$newsource = trim($newsource);
	die "New Source Folder does not exist!\n  Please go to http://my.kayako.com and download the helpesk and geoip files in tar.gz format and extracted the tarball.\n\n"
	unless -d ($newsource);

# SET VARIABLES FOR PERMISSIONS UPDATING AFTER GETTING THE $newsource VARIABLE.
my $dirperm = "find $path/$newsource -type d -exec chmod 777 {} \\;";
my $fileperm = "find $path/$newsource -type f -exec chmod 664 {} \\;";
	
# MOVE KAYAKO PRODUCT FILES FROM UPLOAD TO ROOT IN NEW SOURCEDIR.
print "Moving Product files from the /upload/ directory to the root directory.\n";
system("cp -R $path/$newsource/upload/* $path/$newsource/");
print "Done!\n\n";	

# SET SYMLINKS.
print "Removing old symlink and setting up new symlink for $newsource to kayako_fusion.\n";
system("unlink kayako_fusion");
system("ln -s $newsource kayako_fusion");
	
# COPY FILES THAT NEED TO BE RETAINED.
print "Copying License Key.php\n";
system("cp $path/$oldsource/key.php $path/$newsource/");
print "Done!\n\n";

print "Copying Config file.\n";
system("cp $path/$oldsource/__swift/config/config.php $path/$newsource/__swift/config/");
print "Done!\n\n";

print "Copying existing GEOIP files.\n";
system("cp -R $path/$oldsource/__swift/geoip/ $path/$newsource/__swift/");
print "Done!\n\n";

print "Copying attachements, this may take some time.\n";
system("cp -R $path/$oldsource/__swift/files/ $path/$newsource/__swift/");
print "Done!\n\n";

print "Copying CustomTweaks App.\n";
system("cp -R $path/$oldsource/__apps/customtweaks/ $path/$newsource/__apps/");
print "Done!\n\n";

print "Copying RFC822 Patch required for version prior to 4.68"
system("cp -R $path/RFC822PATCH/class.SWIFT_MailMime.php $path/$newsource/__swift/library/Mail/");
system("cp -R $path/RFC822PATCH/RFC822Extended.php $path/$newsource/__swift/thirdparty/MIME/");
print "Done!\n\n";

# GET NEW GEOIP FILES IF PROVIDED, SKIP IF OMITED.
print 'Is there an updated GEOIP source? (Y/n) ';
	chomp(my $confirm = <STDIN>); #Give user a chance to abort
	if ($confirm =~ /^[Y]?$/i) {      # Match Yy or blank
        print "What is the new GEOIP Source Directory? ";
        	chomp(my $geoip = <STDIN>);
        	$geoip = trim($geoip);
        print "Copying new GEOIP Files.\n";
        system("cp -R $path/$geoip/* $path/$newsource/__swift/geoip/");
        print "Done!\n\n";
    } elsif ($confirm =~ /^[N]$/i) {  # Match Nn
}

# CHECK TO SEE IF WERE GOING TO RUN THE RICH TEXT EDITOR PATCH.
print 'Install the patch for the TinyMCE rich text editor?  (Y/n) ';
	chomp(my $patch = <STDIN>);
	if ($patch =~ /^[Y]?$/i) {      # Match Yy or blank
        print "Installing TinyMCE Patch.\n";
		
		# GET PATCH SOURCE FOLDER.
		print 'Folder (version) name: ';
		chomp(my $version = <STDIN>);
		$version = trim($version);
		die "That folder does not exist!\n"
		unless -e ($version);       
       	print "Done!\n\n";
       	
       	# COPY FILES.
		print "Copying Files Now.\n";
		system("cp $path/$version/settings.php $path/kayako_fusion/__swift/locale/en-us/");
		system("cp $path/$version/settings.xml $path/kayako_fusion/__apps/tickets/config/");
		system("cp $path/$version/class.SWIFT_UserInterfaceTab.php $path/kayako_fusion/__swift/apps/base/library/UserInterface/");
		system("cp $path/$version/class.SWIFT_UserInterfaceTab.php $path/kayako_fusion/__swift/apps/base/library/User");
		system("cp $path/$version/class.Controller_Ticket.php $path/kayako_fusion/__apps/tickets/staff/");
		system("cp $path/$version/class.View_Ticket.php $path/kayako_fusion/__apps/tickets/staff/");
		system("cp $path/$version/core.js $path/kayako_fusion/__swift/apps/base/javascript/__cp/thirdparty/legacy/");
		system("cp $path/$version/class.Controller_ArticleManager.php $path/kayako_fusion/__apps/knowledgebase/staff/");
		system("cp $path/$version/class.SWIFT_TicketPost.php $path/kayako_fusion/__apps/tickets/models/Ticket/");
		print "Done!\n\n";
		
		# SET DIRECTORY AND FILE PERMISSIONS.
		print "Setting Ownership and Directory/File permissions.\n";
		system("chown -R $apache $path/$newsource");
		system("chmod 777 $path/$newsource");
		system("$dirperm");
		system("$fileperm");
		print "Done!\n\n";

		# FORCE A CACHE UPDATE.
		print "Updating helpdesk cache\n";
		system ("curl $hdurl/staff/index.php?/Core/Default/RebuildCache");
		print "Done!\n";
		
		print "Upgrade complete.  Please go to $hdurl/setup/ to run the installer and finish the upgrade.\n\n";
		exit;

    } elsif ($confirm =~ /^[N]$/i) {  # Match Nn
}

# SET DIRECTORY AND FILE PERMISSIONS.
print "Setting Ownership and Directory/File permissions.\n";
system("chown -R $apache $path/$newsource");
system("chmod 777 $path/$newsource");
system("$dirperm");
system("$fileperm");
print "Done!\n\n";

print "Upgrade complete.  Please go to $hdurl/setup/ to run the installer and finish the upgrade.\n\n";
exit;

