# Copyright (c) 2009-2013 Yubico AB
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

VERSION = 2.27
PACKAGE = yubikey-val
CODE = COPYING Makefile NEWS README ykval-checksum-clients		\
	ykval-common.php ykval-config.php ykval-db.php ykval-db.sql	\
	ykval-export ykval-import ykval-log.php ykval-ping.php	\
	ykval-queue ykval-revoke.php ykval-synclib.php		\
	ykval-sync.php ykval-verify.php ykval-export-clients 	\
	ykval-import-clients ykval-db-oci.php ykval-db-pdo.php	\
	ykval-db.oracle.sql ykval-resync.php ykval-checksum-deactivated	\
	ykval-synchronize ykval-gen-clients
MANS = ykval-queue.1 ykval-import.1 ykval-export.1		\
	ykval-import-clients.1 ykval-export-clients.1		\
	ykval-checksum-clients.1 ykval-checksum-deactivated.1	\
	ykval-synchronize.1 ykval-gen-clients.1
MUNIN = ykval-munin-ksmlatency.php ykval-munin-vallatency.php	\
	ykval-munin-queuelength.php ykval-munin-responses.pl \
	ykval-munin-yubikeystats.php ykval-munin-ksmresponses.pl
DOCS = doc/GeneratingClients.adoc doc/GettingStartedWritingClients.adoc \
	doc/ImportExportData.adoc doc/Installation.adoc doc/MakeRelease.adoc \
	doc/MuninProbes.adoc doc/RevocationService.adoc \
	doc/ServerReplicationProtocol.adoc doc/SyncMonitor.adoc \
	doc/Troubleshooting.adoc doc/ValidationProtocolV20.adoc \
	doc/ValidationServerAlgorithm.adoc doc/YubiKeyInfoFormat.adoc

all:
	@echo "Try 'make install' or 'make symlink'."
	@echo "Docs: https://developers.yubico.com/yubikey-val/doc/Installation.html"
	@exit 1

# Installation rules.

etcprefix = /etc/yubico/val
sbinprefix = /usr/sbin
phpprefix = /usr/share/yubikey-val
docprefix = /usr/share/doc/yubikey-val
manprefix = /usr/share/man/man1
muninprefix = /usr/share/munin/plugins
wwwgroup = www-data

install:
	install -D --mode 644 ykval-verify.php $(DESTDIR)$(phpprefix)/ykval-verify.php
	install -D --mode 644 ykval-common.php $(DESTDIR)$(phpprefix)/ykval-common.php
	install -D --mode 644 ykval-synclib.php $(DESTDIR)$(phpprefix)/ykval-synclib.php
	install -D --mode 644 ykval-sync.php $(DESTDIR)$(phpprefix)/ykval-sync.php
	install -D --mode 644 ykval-resync.php $(DESTDIR)$(phpprefix)/ykval-resync.php
	install -D --mode 644 ykval-db.php $(DESTDIR)$(phpprefix)/ykval-db.php
	install -D --mode 644 ykval-db-pdo.php $(DESTDIR)$(phpprefix)/ykval-db-pdo.php
	install -D --mode 644 ykval-db-oci.php $(DESTDIR)$(phpprefix)/ykval-db-oci.php
	install -D --mode 644 ykval-log.php $(DESTDIR)$(phpprefix)/ykval-log.php
	install -D ykval-queue $(DESTDIR)$(sbinprefix)/ykval-queue
	install -D ykval-synchronize $(DESTDIR)$(sbinprefix)/ykval-synchronize
	install -D ykval-export $(DESTDIR)$(sbinprefix)/ykval-export
	install -D ykval-import $(DESTDIR)$(sbinprefix)/ykval-import
	install -D ykval-gen-clients $(DESTDIR)$(sbinprefix)/ykval-gen-clients
	install -D ykval-export-clients $(DESTDIR)$(sbinprefix)/ykval-export-clients
	install -D ykval-import-clients $(DESTDIR)$(sbinprefix)/ykval-import-clients
	install -D ykval-checksum-clients $(DESTDIR)$(sbinprefix)/ykval-checksum-clients
	install -D ykval-checksum-deactivated $(DESTDIR)$(sbinprefix)/ykval-checksum-deactivated
	install -D ykval-queue.1 $(DESTDIR)$(manprefix)/ykval-queue.1
	install -D ykval-synchronize.1 $(DESTDIR)$(manprefix)/ykval-synchronize.1
	install -D ykval-import.1 $(DESTDIR)$(manprefix)/ykval-import.1
	install -D ykval-export.1 $(DESTDIR)$(manprefix)/ykval-export.1
	install -D ykval-gen-clients.1 $(DESTDIR)$(manprefix)/ykval-gen-clients.1
	install -D ykval-import-clients.1 $(DESTDIR)$(manprefix)/ykval-import-clients.1
	install -D ykval-export-clients.1 $(DESTDIR)$(manprefix)/ykval-export-clients.1
	install -D ykval-checksum-clients.1 $(DESTDIR)$(manprefix)/ykval-checksum-clients.1
	install -D ykval-checksum-deactivated.1 $(DESTDIR)$(manprefix)/ykval-checksum-deactivated.1
	install -D ykval-munin-ksmlatency.php $(DESTDIR)$(muninprefix)/ykval_ksmlatency
	install -D ykval-munin-vallatency.php $(DESTDIR)$(muninprefix)/ykval_vallatency
	install -D ykval-munin-queuelength.php $(DESTDIR)$(muninprefix)/ykval_queuelength
	install -D ykval-munin-responses.pl $(DESTDIR)$(muninprefix)/ykval_responses
	install -D ykval-munin-ksmresponses.pl $(DESTDIR)$(muninprefix)/ykval_ksmresponses
	install -D ykval-munin-yubikeystats.php $(DESTDIR)$(muninprefix)/ykval_yubikeystats
	install -D --backup --mode 640 --group $(wwwgroup) ykval-config.php $(DESTDIR)$(etcprefix)/ykval-config.php
	install -D --mode 644 ykval-db.sql $(DESTDIR)$(docprefix)/ykval-db.sql
	install -D --mode 644 ykval-db.oracle.sql $(DESTDIR)$(docprefix)/ykval-db.oracle.sql
	install -D --mode 644 $(DOCS) $(DESTDIR)$(docprefix)/

wwwprefix = /var/www/wsapi

symlink:
	install -d $(DESTDIR)$(wwwprefix)/2.0
	ln -sf $(phpprefix)/ykval-verify.php $(DESTDIR)$(wwwprefix)/2.0/verify.php
	ln -sf $(phpprefix)/ykval-sync.php $(DESTDIR)$(wwwprefix)/2.0/sync.php
	ln -sf $(phpprefix)/ykval-resync.php $(DESTDIR)$(wwwprefix)/2.0/resync.php
	ln -sf 2.0/verify.php $(DESTDIR)$(wwwprefix)/verify.php

revoke:
	install -D --mode 644 ykval-revoke.php $(DESTDIR)$(phpprefix)/ykval-revoke.php
	ln -sf $(phpprefix)/ykval-revoke.php $(DESTDIR)$(wwwprefix)/revoke.php

# Maintainer rules.

PROJECT = $(PACKAGE)

$(PACKAGE)-$(VERSION).tgz: $(FILES)
	mkdir $(PACKAGE)-$(VERSION) $(PACKAGE)-$(VERSION)/doc
	cp $(CODE) $(MANS) $(MUNIN) $(PACKAGE)-$(VERSION)/
	cp $(DOCS) $(PACKAGE)-$(VERSION)/doc/
	git2cl > $(PACKAGE)-$(VERSION)/ChangeLog
	tar cfz $(PACKAGE)-$(VERSION).tgz $(PACKAGE)-$(VERSION)
	rm -rf $(PACKAGE)-$(VERSION)

dist: $(PACKAGE)-$(VERSION).tgz

clean:
	rm -f *~
	rm -rf $(PACKAGE)-$(VERSION)

release: dist
	@if test -z "$(KEYID)"; then \
		echo "Try this instead:"; \
		echo "  make release KEYID=[PGPKEYID]"; \
		echo "For example:"; \
		echo "  make release KEYID=2117364A"; \
		exit 1; \
	fi
	@head -1 NEWS | grep -q "Version $(VERSION) (released `date -I`)" || \
		(echo 'error: You need to update date/version in NEWS'; exit 1)
	@if test ! -d "$(YUBICO_GITHUB_REPO)"; then \
		echo "yubico.github.com repo not found!"; \
		echo "Make sure that YUBICO_GITHUB_REPO is set"; \
		exit 1; \
	fi
	gpg --detach-sign --default-key $(KEYID) $(PACKAGE)-$(VERSION).tgz
	gpg --verify $(PACKAGE)-$(VERSION).tgz.sig
	git tag -u $(KEYID) -m $(VERSION) $(PACKAGE)-$(VERSION)
	@echo "Release created and tagged, remember to git push && git push --tags"
	$(YUBICO_GITHUB_REPO)/publish $(PROJECT) $(VERSION) $(PACKAGE)-$(VERSION).tgz*
