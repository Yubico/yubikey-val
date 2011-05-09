VERSION = 2.9
PACKAGE = yubikey-val
CODE = COPYING Makefile NEWS ykval-checksum-clients.php			\
	ykval-common.php ykval-config.php ykval-db.php ykval-db.sql	\
	ykval-export.php ykval-import.php ykval-log.php ykval-ping.php	\
	ykval-queue.php ykval-revoke.php ykval-synclib.php		\
	ykval-sync.php ykval-verify.php
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
	install -D ykval-verify.php $(phpprefix)/ykval-verify.php
	install -D ykval-common.php $(phpprefix)/ykval-common.php
	install -D ykval-synclib.php $(phpprefix)/ykval-synclib.php
	install -D ykval-sync.php $(phpprefix)/ykval-sync.php
	install -D ykval-db.php $(phpprefix)/ykval-db.php
	install -D ykval-log.php $(phpprefix)/ykval-log.php
	install -D ykval-queue.php $(sbinprefix)/ykval-queue
	install -D ykval-export.php $(sbinprefix)/ykval-export
	install -D ykval-import.php $(sbinprefix)/ykval-import
	install -D ykval-checksum-clients.php $(sbinprefix)/ykval-checksum-clients
	install -D ykval-munin-ksmlatency.php $(muninprefix)/ykval_ksmlatency
	install -D ykval-munin-vallatency.php $(muninprefix)/ykval_vallatency
	install -D ykval-munin-queuelength.php $(muninprefix)/ykval_queuelength
	install -D --backup --mode 640 --group $(wwwgroup) ykval-config.php $(etcprefix)/ykval-config.php-template
	install -D ykval-db.sql $(docprefix)/ykval-db.sql
	install -D $(DOCS) $(docprefix)/

wwwprefix = /var/www/wsapi

symlink:
	install -d $(wwwprefix)/2.0
	ln -sf $(phpprefix)/ykval-verify.php $(wwwprefix)/2.0/verify.php
	ln -sf $(phpprefix)/ykval-sync.php $(wwwprefix)/2.0/sync.php
	ln -sf 2.0/verify.php $(wwwprefix)/verify.php

revoke:
	install -D ykval-revoke.php $(phpprefix)/ykval-revoke.php
	ln -sf $(phpprefix)/ykval-revoke.php $(wwwprefix)/revoke.php

# Maintainer rules.

PROJECT=yubikey-val-server-php
USER=simon@yubico.com
KEYID=2117364A

$(PACKAGE)-$(VERSION).tgz: $(FILES)
	mkdir $(PACKAGE)-$(VERSION) $(PACKAGE)-$(VERSION)/doc
	cp $(CODE) $(PACKAGE)-$(VERSION)/
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
	svn copy https://$(PROJECT).googlecode.com/svn/trunk/ \
	 https://$(PROJECT).googlecode.com/svn/tags/$(PACKAGE)-$(VERSION) \
	 -m "Tagging the $(VERSION) release of the $(PACKAGE) project."
	googlecode_upload.py -s "OpenPGP signature for $(PACKAGE) $(VERSION)." \
	 -p $(PROJECT) -u $(USER) $(PACKAGE)-$(VERSION).tgz.sig
	googlecode_upload.py -s "$(PACKAGE) $(VERSION)." \
	 -p $(PROJECT) -u $(USER) $(PACKAGE)-$(VERSION).tgz 
