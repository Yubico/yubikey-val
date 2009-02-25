DROP TABLE IF EXISTS admin;
CREATE TABLE admin (
  id int(10) unsigned NOT NULL auto_increment,
  keyid int NOT NULL default '0',
  note varchar(45) default NULL,
  pin varchar(120) default NULL,
  last_access datetime default NULL,
  ip varchar(45) default NULL,
  creation datetime default NULL,
  client int NOT NULL default '0',
  timeout int unsigned NOT NULL default '3600',
  PRIMARY KEY  (id),
  KEY FK_admin_2 (keyid),
  KEY FK_admin_1 (client),
  CONSTRAINT FK_admin_1 FOREIGN KEY (client) REFERENCES clients (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT FK_admin_2 FOREIGN KEY (keyid) REFERENCES yubikeys (id) ON DELETE CASCADE ON UPDATE CASCADE
);

DROP TABLE IF EXISTS buyers;
CREATE TABLE buyers (
  id int unsigned NOT NULL auto_increment,
  email varchar(100) default NULL,
  created datetime default NULL,
  addr varchar(200) default NULL,
  qty int unsigned default NULL,
  client_id int NOT NULL default '0',
  name varchar(45) default NULL,
  PRIMARY KEY  (id),
  KEY FK_client_id_1 (client_id),
  CONSTRAINT FK_client_info_1 FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE CASCADE ON UPDATE CASCADE
);

DROP TABLE IF EXISTS clients;
CREATE TABLE clients (
  id int NOT NULL auto_increment,
  perm_id int default NULL,
  active boolean default true,
  created datetime NOT NULL,
  email varchar(255) NOT NULL default '',
  secret varchar(60) NOT NULL default '',
  notes varchar(100) default NULL,
  chk_sig boolean default false,
  chk_owner boolean default false,
  chk_time boolean default true,
  PRIMARY KEY  (id),
  UNIQUE KEY email (email),
  KEY perm_id (perm_id),
  CONSTRAINT clients_ibfk_1 FOREIGN KEY (perm_id) REFERENCES perms (id)
);

DROP TABLE IF EXISTS history;
CREATE TABLE history (
  id int unsigned NOT NULL auto_increment,
  usrid int unsigned NOT NULL default '0',
  note varchar(45) NOT NULL default '',
  ip varchar(45) NOT NULL default '',
  creation datetime NOT NULL,
  keyid int unsigned NOT NULL default '0',
  PRIMARY KEY  (id),
  KEY FK_hist_1 (usrid)
);

DROP TABLE IF EXISTS perms;
CREATE TABLE perms (
  id int NOT NULL auto_increment,
  verify_otp boolean default false,
  add_clients boolean default false,
  delete_clients boolean default false,
  add_keys boolean default false,
  delete_keys boolean default false,
  PRIMARY KEY  (id)
);

DROP TABLE IF EXISTS yubikeys;
CREATE TABLE yubikeys (
  id int NOT NULL auto_increment,
  client_id int NOT NULL default '0',
  active boolean default true,
  created datetime NOT NULL,
  accessed datetime,
  tokenId varchar(60),
  userId varchar(60) NOT NULL,
  secret varchar(60) NOT NULL,
  counter int,
  low int,
  high int,
  notes varchar(100),
  serial varchar(45),
  sessionUse int,
  PRIMARY KEY  (id),
  UNIQUE KEY userId (userId),
  UNIQUE KEY tokenId (tokenId),
  UNIQUE KEY sn (serial),
  KEY client_id (client_id),
  CONSTRAINT yubikeys_ibfk_1 FOREIGN KEY (client_id) REFERENCES clients (id)
);
