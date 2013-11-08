#!/usr/bin/perl

use IO::Socket::INET;

use strict;
use warnings;

my %otps = (
  'idkfefrdhtrutjduvtcjbfeuvhehdvjjlbchtlenfgku' => 'OK counter=0001 low=8d40 high=0f use=00',
  'idkfefrdhtrutjduvtcjbfeuvhehdvjjlbchtlenfgkv' => 'ERR Corrupt OTP',
);

my $socket = new IO::Socket::INET (
    LocalHost => '127.0.0.1',
    LocalPort => '80',
    Proto => 'tcp',
    Listen => 10,
    Reuse => 1
) or die "Oops: $! \n";

while (1) {
  my $clientsocket = $socket->accept();
  my $clientdata = <$clientsocket>;
  my $ret = "ERR Unknown yubikey";
  if($clientdata =~ m/otp=([cbdefghijklnrtuv]+)/) {
    my $otp = $1;
    if($otps{$otp}) {
      $ret = $otps{$otp};
    }
  }
  print $clientsocket "\n$ret\n";
  close $clientsocket;
}
