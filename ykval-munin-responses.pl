#!/usr/bin/perl
#%# family=auto
#%# capabilities=autoconf

# Copyright (c) 2012-2013 Yubico AB
# All rights reserved.
#
# Redistribution and use in source and binary forms, with or without
# modification, are permitted provided that the following conditions are
# met:
#
#   * Redistributions of source code must retain the above copyright
#     notice, this list of conditions and the following disclaimer.
#
#   * Redistributions in binary form must reproduce the above
#     copyright notice, this list of conditions and the following
#     disclaimer in the documentation and/or other materials provided
#     with the distribution.
#
# THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
# "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
# LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
# A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
# OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
# SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
# LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
# DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
# THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
# (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
# OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

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
