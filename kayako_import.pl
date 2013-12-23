#!/usr/bin/perl
####################################################################################
# A quick perl script that will import the production Kayako DB export into our test 
# environment and provide housekeeping post import.  
# 12/23/2013
# Version 1.2
# jchieppa@gmail.com
####################################################################################

use strict;
use warnings;
use autodie;
use DBI;
use LWP::Simple;
        
# CONFIG VARIABLES
my $platform = "mysql";
my $database = "kayako";
my $host     = "localhost";
my $username = "username";
my $password = "password";
my $hdurl = "http://yourhelpdeskurl";

# FUNCTION TO TRIM WHITESPACE
sub trim($)
{
    my $string = shift;
    $string =~ s/^\s+//;
    $string =~ s/\s+$//;
    return $string;
}

# DATABASE UPDATE VARIABLES
my $url = "update swsettings set data = '$hdurl/' where vkey='general_producturl'";
my $emailclose = "update swautocloserules set isenabled = '0'";
my $queue = "update swemailqueues set isenabled = '0'";

# DATA SOURCE NAME
my $dsn = "dbi:$platform:$database:$host";

# GET FILE TO IMPORT FROM
print 'Database export filename to import from: ';
	chomp(my $import = <STDIN>);
	$import = trim($import);
	if (-e $import)
{ 

my $dbimport = "mysql -u $username -p$password kayako < $import";
print "Importing $import\.  Please be patient this may take several minutes.\n";

# IMPORT THE SQL FILE

system ($dbimport);

# PERL DBI CONNECT
my $connect = DBI->connect($dsn, $username, $password) or die $DBI::errstr;

print "Connected to the Database\n";
print "\n";

# SET THE HELPDESK URL FOR THE DEV ENVIRONMENT
print "Updating Helpdesk URL\n";
$connect->do ($url) or die "SQL Error: $DBI::errstr";
print "Done!\n";
print "\n";

sleep 1;

# DISABLE EMAIL QUEUES TO PREVENT DEV FROM PICKING UP SUPPORT EMAILS
print "Disabling Email Queue\(s\)\n";
$connect->do ($queue) or die "SQL Error: $DBI::errstr";
print "Done!\n";
print "\n";

sleep 1;

# DISABLE AUTO CLOSURE RULES
print "Disabling Auto Closure Rule\(s\)\n";
$connect->do ($emailclose) or die "SQL Error: $DBI::errstr";
print "Done!\n";
print "\n";

# CLOSE MYSQL CONNECTION GRACEFULLY
$connect->disconnect;

# FORCE A CACHE UPDATE.
print "Updating helpdesk cache\n";
system ("curl $hdurl/staff/index.php?/Core/Default/RebuildCache");
print "Done!\n";
print "\n";

print "All finished, quitting!\n";

exit;

}
	else { print "File not found.  Aborting!\n";
        exit;
    }
    



	


