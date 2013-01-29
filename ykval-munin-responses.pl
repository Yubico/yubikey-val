#!/usr/bin/perl
#%# family=auto
#%# capabilities=autoconf

use strict;
use warnings;

my @types = qw/OK BAD_OTP MISSING_PARAMETER BACKEND_ERROR BAD_SIGNATURE DELAYED_OTP NO_SUCH_CLIENT NOT_ENOUGH_ANSWERS REPLAYED_REQUEST REPLAYED_OTP OPERATION_NOT_ALLOWED/;
my $logfile = "/var/log/syslog";

if(@ARGV > 0) {
  if($ARGV[0] eq "autoconf") {
    print "yes\n";
    exit 0;
  } elsif($ARGV[0] eq "config") {
    print "multigraph ykval_responses\n";
    print "graph_title YK-VAL response types\n";
    print "graph_vlabel responses\n";
    print "graph_category ykval\n";

    foreach my $type (@types) {
      print "${type}.label ${type}\n";
      print "${type}.type DERIVE\n";
      print "${type}.info Responses\n";
      print "${type}.min 0\n";
      print "${type}.draw LINE1\n";
    }
    exit 0
  }
  print "unknown command '${ARGV[0]}'\n";
  exit 1
}

my %statuses = map { $_ => 0 } @types;

my $reg = qr/status=([A-Z_]+)/;
open (LOGFILE, "grep 'ykval-verify.*Response' $logfile |");
while(<LOGFILE>) {
  next unless /$reg/;
  $statuses{$1}++;
}
close LOGFILE;

print "multigraph ykval_responses\n";
foreach my $type (@types) {
  print "${type}.value ${statuses{$type}}\n";
}
exit 0
