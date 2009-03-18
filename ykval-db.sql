// DROP DATABASE yubico;
// CREATE DATABASE yubico;
// USE yubico;

// DROP TABLE IF EXISTS clients;
// DROP TABLE IF EXISTS perms;
// DROP TABLE IF EXISTS yubikeys;

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

CREATE TABLE perms (
  id int NOT NULL auto_increment,
  verify_otp boolean default false,
  add_clients boolean default false,
  delete_clients boolean default false,
  add_keys boolean default false,
  delete_keys boolean default false,
  PRIMARY KEY  (id)
);

CREATE TABLE yubikeys (
  id int NOT NULL UNIQUE auto_increment,
  client_id int NOT NULL default '0',
  active boolean default true,
  created datetime NOT NULL,
  accessed datetime,
  tokenId varchar(60) binary unique not null,
  userId varchar(60) UNIQUE NOT NULL,
  secret varchar(60) NOT NULL,
  counter int,
  low int,
  high int,
  notes varchar(100),
  serial varchar(45) UNIQUE,
  sessionUse int,
  PRIMARY KEY  (id),
  KEY client_id (client_id),
  CONSTRAINT yubikeys_ibfk_1 FOREIGN KEY (client_id) REFERENCES clients (id)
);
