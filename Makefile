VERSION = 2.21
PACKAGE = yubikey-val
CODE = COPYING Makefile NEWS ykval-checksum-clients			\
	ykval-common.php ykval-config.php ykval-db.php ykval-db.sql	\
	ykval-export ykval-import ykval-log.php ykval-ping.php	\
	ykval-queue ykval-revoke.php ykval-synclib.php		\
	ykval-sync.php ykval-verify.php ykval-export-clients 	\
	ykval-import-clients ykval-db-oci.php ykval-db-pdo.php	\
	ykval-db.oracle.sql ykval-resync.php ykval-checksum-deactivated
MANS = ykval-queue.1 ykval-import.1 ykval-export.1		\
	ykval-import-clients.1 ykval-export-clients.1		\
	ykval-checksum-clients.1 ykval-checksum-deactivated.1
MUNIN = ykval-munin-ksmlatency.php ykval-munin-vallatency.php	\
	ykval-munin-queuelength.php ykval-munin-responses.pl \
	ykval-munin-yubikeystats.php
DOCS = doc/ClientInfoFormat.wiki doc/Installation.wiki			\
	doc/RevocationService.wiki doc/ServerReplicationProtocol.wiki	\
	doc/SyncMonitor.wiki doc/Troubleshooting.wiki
TMPDIR = /tmp/tmp.yubikey-val

all:
	@echo "Try 'make install' or 'make symlink'."
	@echo "Docs: https://github.com/Yubico/yubikey-val/wiki/Installation"
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
	install -D ykval-export $(DESTDIR)$(sbinprefix)/ykval-export
	install -D ykval-import $(DESTDIR)$(sbinprefix)/ykval-import
	install -D ykval-export-clients $(DESTDIR)$(sbinprefix)/ykval-export-clients
	install -D ykval-import-clients $(DESTDIR)$(sbinprefix)/ykval-import-clients
	install -D ykval-checksum-clients $(DESTDIR)$(sbinprefix)/ykval-checksum-clients
	install -D ykval-checksum-deactivated $(DESTDIR)$(sbinprefix)/ykval-checksum-deactivated
	install -D ykval-queue.1 $(DESTDIR)$(manprefix)/ykval-queue.1
	install -D ykval-import.1 $(DESTDIR)$(manprefix)/ykval-import.1
	install -D ykval-export.1 $(DESTDIR)$(manprefix)/ykval-export.1
	install -D ykval-import-clients.1 $(DESTDIR)$(manprefix)/ykval-import-clients.1
	install -D ykval-export-clients.1 $(DESTDIR)$(manprefix)/ykval-export-clients.1
	install -D ykval-checksum-clients.1 $(DESTDIR)$(manprefix)/ykval-checksum-clients.1
	install -D ykval-checksum-deactivated.1 $(DESTDIR)$(manprefix)/ykval-checksum-deactivated.1
	install -D ykval-munin-ksmlatency.php $(DESTDIR)$(muninprefix)/ykval_ksmlatency
	install -D ykval-munin-vallatency.php $(DESTDIR)$(muninprefix)/ykval_vallatency
	install -D ykval-munin-queuelength.php $(DESTDIR)$(muninprefix)/ykval_queuelength
	install -D ykval-munin-responses.pl $(DESTDIR)$(muninprefix)/ykval_responses
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

$(PACKAGE)-$(VERSION).tgz: $(FILES)
	git submodule init
	git submodule update
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
	gpg --detach-sign --default-key $(KEYID) $(PACKAGE)-$(VERSION).tgz
	gpg --verify $(PACKAGE)-$(VERSION).tgz.sig

	git tag -u $(KEYID) -m $(VERSION) $(PACKAGE)-$(VERSION)
	git push
	git push --tags
	mkdir -p $(TMPDIR)
	mv $(PACKAGE)-$(VERSION).tgz $(TMPDIR)
	mv $(PACKAGE)-$(VERSION).tgz.sig $(TMPDIR)

	git checkout gh-pages
	mv $(TMPDIR)/$(PACKAGE)-$(VERSION).tgz releases/
	mv $(TMPDIR)/$(PACKAGE)-$(VERSION).tgz.sig releases/
	git add releases/$(PACKAGE)-$(VERSION).tgz
	git add releases/$(PACKAGE)-$(VERSION).tgz.sig
	rmdir --ignore-fail-on-non-empty $(TMPDIR)

	x=$$(ls -1v releases/*.tgz | awk -F\- '{print $$3}' | sed 's/.tgz//' | paste -sd ',' - | sed 's/,/, /g' | sed 's/\([0-9.]\{1,\}\)/"\1"/g');sed -i -e "2s/\[.*\]/[$$x]/" releases.html
	git add releases.html
	git commit -m "Added tarball for release $(VERSION)"
	git push
	git checkout master
