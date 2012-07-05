VERSION = 2.19
PACKAGE = yubikey-val
CODE = COPYING Makefile NEWS ykval-checksum-clients.php			\
	ykval-common.php ykval-config.php ykval-db.php ykval-db.sql	\
	ykval-export.php ykval-import.php ykval-log.php ykval-ping.php	\
	ykval-queue.php ykval-revoke.php ykval-synclib.php		\
	ykval-sync.php ykval-verify.php ykval-export-clients.php 	\
	ykval-import-clients.php ykval-db-oci.php ykval-db-pdo.php	\
	ykval-db.oracle.sql ykval-resync.php
MUNIN = ykval-munin-ksmlatency.php ykval-munin-vallatency.php	\
	ykval-munin-queuelength.php ykval-munin-responses.pl \
	ykval-munin-yubikeystats.php
DOCS = doc/ClientInfoFormat.wiki doc/Installation.wiki			\
	doc/RevocationService.wiki doc/ServerReplicationProtocol.wiki	\
	doc/SyncMonitor.wiki doc/Troubleshooting.wiki

all:
	@echo "Try 'make install' or 'make symlink'."
	@echo "Docs: http://code.google.com/p/$(PROJECT)/wiki/Installation"
	@exit 1

# Installation rules.

etcprefix = /etc/ykval
sbinprefix = /usr/sbin
phpprefix = /usr/share/ykval
docprefix = /usr/share/doc/ykval
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
	install -D ykval-queue.php $(DESTDIR)$(sbinprefix)/ykval-queue
	install -D ykval-export.php $(DESTDIR)$(sbinprefix)/ykval-export
	install -D ykval-import.php $(DESTDIR)$(sbinprefix)/ykval-import
	install -D ykval-export-clients.php $(DESTDIR)$(sbinprefix)/ykval-export-clients
	install -D ykval-import-clients.php $(DESTDIR)$(sbinprefix)/ykval-import-clients
	install -D ykval-checksum-clients.php $(DESTDIR)$(sbinprefix)/ykval-checksum-clients
	install -D ykval-munin-ksmlatency.php $(DESTDIR)$(muninprefix)/ykval_ksmlatency
	install -D ykval-munin-vallatency.php $(DESTDIR)$(muninprefix)/ykval_vallatency
	install -D ykval-munin-queuelength.php $(DESTDIR)$(muninprefix)/ykval_queuelength
	install -D ykval-munin-responses.pl $(DESTDIR)$(muninprefix)/ykval_responses
	install -D ykval-munin-yubikeystats.php $(DESTDIR)$(muninprefix)/ykval_yubikeystats
	install -D --backup --mode 640 --group $(wwwgroup) ykval-config.php $(DESTDIR)$(etcprefix)/ykval-config.php-template
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

PROJECT=yubikey-val-server-php

$(PACKAGE)-$(VERSION).tgz: $(FILES)
	git submodule init
	git submodule update
	mkdir $(PACKAGE)-$(VERSION) $(PACKAGE)-$(VERSION)/doc
	cp $(CODE) $(MUNIN) $(PACKAGE)-$(VERSION)/
	cp $(DOCS) $(PACKAGE)-$(VERSION)/doc/
	tar cfz $(PACKAGE)-$(VERSION).tgz $(PACKAGE)-$(VERSION)
	rm -rf $(PACKAGE)-$(VERSION)

dist: $(PACKAGE)-$(VERSION).tgz

clean:
	rm -f *~
	rm -rf $(PACKAGE)-$(VERSION)

release: dist
	@if test -z "$(USER)" || test -z "$(KEYID)"; then \
		echo "Try this instead:"; \
		echo "  make release USER=[GOOGLEUSERNAME] KEYID=[PGPKEYID]"; \
		echo "For example:"; \
		echo "  make release USER=simon@josefsson.org KEYID=2117364A"; \
		exit 1; \
	fi
	@head -1 NEWS | grep -q "Version $(VERSION) released `date -I`" || \
		(echo 'error: You need to update date/version in NEWS'; exit 1)
	gpg --detach-sign --default-key $(KEYID) $(PACKAGE)-$(VERSION).tgz
	gpg --verify $(PACKAGE)-$(VERSION).tgz.sig
	git push
	git tag -u $(KEYID)! -m $(VERSION) yubikey-val-$(VERSION)
	git push --tags
	googlecode_upload.py -s "OpenPGP signature for $(PACKAGE) $(VERSION)." \
	 -p $(PROJECT) -u $(USER) $(PACKAGE)-$(VERSION).tgz.sig
	googlecode_upload.py -s "$(PACKAGE) $(VERSION)." \
	 -p $(PROJECT) -u $(USER) $(PACKAGE)-$(VERSION).tgz 
