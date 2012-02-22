VERSION = 2.12
PACKAGE = yubikey-val
CODE = COPYING Makefile NEWS ykval-checksum-clients.php			\
	ykval-common.php ykval-config.php ykval-db.php ykval-db.sql	\
	ykval-export.php ykval-import.php ykval-log.php ykval-ping.php	\
	ykval-queue.php ykval-revoke.php ykval-synclib.php		\
	ykval-sync.php ykval-verify.php
MUNIN = ykval-munin-ksmlatency.php ykval-munin-vallatency.php	\
	ykval-munin-queuelength.php
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
	install -D --mode 644 ykval-db.php $(DESTDIR)$(phpprefix)/ykval-db.php
	install -D --mode 644 ykval-log.php $(DESTDIR)$(phpprefix)/ykval-log.php
	install -D ykval-queue.php $(DESTDIR)$(sbinprefix)/ykval-queue
	install -D ykval-export.php $(DESTDIR)$(sbinprefix)/ykval-export
	install -D ykval-import.php $(DESTDIR)$(sbinprefix)/ykval-import
	install -D ykval-checksum-clients.php $(DESTDIR)$(sbinprefix)/ykval-checksum-clients
	install -D ykval-munin-ksmlatency.php $(DESTDIR)$(muninprefix)/ykval_ksmlatency
	install -D ykval-munin-vallatency.php $(DESTDIR)$(muninprefix)/ykval_vallatency
	install -D ykval-munin-queuelength.php $(DESTDIR)$(muninprefix)/ykval_queuelength
	install -D --backup --mode 640 --group $(wwwgroup) ykval-config.php $(DESTDIR)$(etcprefix)/ykval-config.php-template
	install -D --mode 644 ykval-db.sql $(DESTDIR)$(docprefix)/ykval-db.sql
	install -D --mode 644 $(DOCS) $(DESTDIR)$(docprefix)/

wwwprefix = /var/www/wsapi

symlink:
	install -d $(DESTDIR)$(wwwprefix)/2.0
	ln -sf $(phpprefix)/ykval-verify.php $(DESTDIR)$(wwwprefix)/2.0/verify.php
	ln -sf $(phpprefix)/ykval-sync.php $(DESTDIR)$(wwwprefix)/2.0/sync.php
	ln -sf 2.0/verify.php $(DESTDIR)$(wwwprefix)/verify.php

revoke:
	install -D --mode 644 ykval-revoke.php $(DESTDIR)$(phpprefix)/ykval-revoke.php
	ln -sf $(phpprefix)/ykval-revoke.php $(DESTDIR)$(wwwprefix)/revoke.php

# Maintainer rules.

PROJECT=yubikey-val-server-php
USER=simon@josefsson.org
KEYID=2117364A

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
	gpg --detach-sign --default-key $(KEYID) $(PACKAGE)-$(VERSION).tgz
	gpg --verify $(PACKAGE)-$(VERSION).tgz.sig
	git push
	git tag -u $(KEYID)! -m $(VERSION) v$(VERSION)
	git push --tags
	googlecode_upload.py -s "OpenPGP signature for $(PACKAGE) $(VERSION)." \
	 -p $(PROJECT) -u $(USER) $(PACKAGE)-$(VERSION).tgz.sig
	googlecode_upload.py -s "$(PACKAGE) $(VERSION)." \
	 -p $(PROJECT) -u $(USER) $(PACKAGE)-$(VERSION).tgz 
