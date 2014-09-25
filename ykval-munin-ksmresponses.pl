#!/usr/bin/perl
#%# family=auto
#%# capabilities=autoconf

# Copyright (c) 2012-2014 Yubico AB
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

use Env qw/YKVAL_LOGFILE YKVAL_KSMS/;

my @ksms = split(/ /, $YKVAL_KSMS);
die "YKVAL_KSMS has to be set with the hostnames of any ksms used." unless @ksms;

my $logfile = $YKVAL_LOGFILE;
$logfile = "/var/log/syslog" unless $logfile;

if(@ARGV > 0) {
  if($ARGV[0] eq "autoconf") {
    print "yes\n";
    exit 0;
  } elsif($ARGV[0] eq "config") {
    print "multigraph ykval_ksmresponses\n";
    print "graph_title YK-VAL KSM responses\n";
    print "graph_vlabel responses\n";
    print "graph_category ykval\n";

    foreach my $ksm (@ksms) {
      print "${ksm}.label ${ksm}\n";
      print "${ksm}.type DERIVE\n";
      print "${ksm}.info Responses\n";
      print "${ksm}.min 0\n";
      print "${ksm}.draw LINE1\n";
    }
    exit 0
  }
  print "unknown command '${ARGV[0]}'\n";
  exit 1
}

my %responses = map { $_ => 0 } @ksms;
my $reg = qr/url=https?:\/\/([a-z0-9A-Z_-]+)\./;
open (my $file, "-|", "grep 'YK-KSM errno/error: 0/' $logfile");
while(<$file>) {
  next unless /$reg/;
  $responses{$1}++;
}
close $file;

print "multigraph ykval_ksmresponses\n";
foreach my $ksm (@ksms) {
  print "${ksm}.value ${responses{$ksm}}\n";
}
exit 0
