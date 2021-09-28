Name:           yubikey-val
Version:        2.39
Release:        ARL1%{?dist}
Summary:        The YubiKey Validation Server (YK-VAL) is a server that validates Yubikey One-Time Passwords (OTPs). YK-VAL is written in PHP, for use behind web servers such as Apache.  The server implements the Yubico API protocol and further documentation is also available in the doc/ subdirectory.  This server talks to a KSM service for decrypting the OTPs, to avoid storing any AES keys on the validation server. One implementation of this service is the YubiKey-KSM, and another implementation using the YubiHSM hardware is PyHSM.  Note that version 1.x is a minimal centralized server. Version 2.x is a replicated system that uses multiple machines.
License:        BSD
URL:            https://github.com/Yubico/yubikey-val/releases
Source0:        https://github.com/Yubico/yubikey-val/archive/yubikey-val-%{version}.tar.gz

BuildArch:      noarch
BuildRequires:  make
Requires:       httpd php mariadb php-mysql mariadb-server

%description


%prep
%setup -q


#%build
#make %{?_smp_mflags}


%install
rm -rf $RPM_BUILD_ROOT
%make_install wwwgroup=apache


%files
%{_sysconfdir}/yubico/val/ykval-config.php
%{_sbindir}/ykval-checksum-clients
%{_sbindir}/ykval-checksum-deactivated
%{_sbindir}/ykval-export
%{_sbindir}/ykval-export-clients
%{_sbindir}/ykval-gen-clients
%{_sbindir}/ykval-import
%{_sbindir}/ykval-import-clients
%{_sbindir}/ykval-nagios-queuelength
%{_sbindir}/ykval-queue
%{_sbindir}/ykval-synchronize
%{_prefix}/share/munin/plugins/ykval_ksmlatency
%{_prefix}/share/munin/plugins/ykval_ksmresponses
%{_prefix}/share/munin/plugins/ykval_queuelength
%{_prefix}/share/munin/plugins/ykval_responses
%{_prefix}/share/munin/plugins/ykval_vallatency
%{_prefix}/share/munin/plugins/ykval_yubikeystats
%{_prefix}/share/yubikey-val/ykval-common.php
%{_prefix}/share/yubikey-val/ykval-db-oci.php
%{_prefix}/share/yubikey-val/ykval-db-pdo.php
%{_prefix}/share/yubikey-val/ykval-db.php
%{_prefix}/share/yubikey-val/ykval-log-verify.php
%{_prefix}/share/yubikey-val/ykval-log.php
%{_prefix}/share/yubikey-val/ykval-resync.php
%{_prefix}/share/yubikey-val/ykval-sync.php
%{_prefix}/share/yubikey-val/ykval-synclib.php
%{_prefix}/share/yubikey-val/ykval-verify.php

%doc
%{_docdir}/yubikey-val/Generating_Clients.adoc
%{_docdir}/yubikey-val/Getting_Started_Writing_Clients.adoc
%{_docdir}/yubikey-val/Import_Export_Data.adoc
%{_docdir}/yubikey-val/Installation.adoc
%{_docdir}/yubikey-val/Make_Release.adoc
%{_docdir}/yubikey-val/Munin_Probes.adoc
%{_docdir}/yubikey-val/Revocation_Service.adoc
%{_docdir}/yubikey-val/Server_Replication_Protocol.adoc
%{_docdir}/yubikey-val/Sync_Monitor.adoc
%{_docdir}/yubikey-val/Troubleshooting.adoc
%{_docdir}/yubikey-val/Validation_Protocol_V2.0.adoc
%{_docdir}/yubikey-val/Validation_Server_Algorithm.adoc
%{_docdir}/yubikey-val/YubiKey_Info_Format.adoc
%{_docdir}/yubikey-val/ykval-db.oracle.sql
%{_docdir}/yubikey-val/ykval-db.sql
%{_mandir}/man1/ykval-checksum-clients.1.gz
%{_mandir}/man1/ykval-checksum-deactivated.1.gz
%{_mandir}/man1/ykval-export-clients.1.gz
%{_mandir}/man1/ykval-export.1.gz
%{_mandir}/man1/ykval-gen-clients.1.gz
%{_mandir}/man1/ykval-import-clients.1.gz
%{_mandir}/man1/ykval-import.1.gz
%{_mandir}/man1/ykval-queue.1.gz
%{_mandir}/man1/ykval-synchronize.1.gz


%changelog
* Mon Dec 04 2017 Robert Giles <rgiles@arlut.utexas.edu> - 1:2.39-ARL1
- Initial RHEL7 release, based on Yubico-published 2.39 release.
