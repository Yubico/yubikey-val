-- DROP DATABASE ykval;
CREATE DATABASE ykval;
USE ykval;

CREATE TABLE clients (
  id INT NOT NULL AUTO_INCREMENT,
  active BOOLEAN DEFAULT TRUE,
  created DATETIME NOT NULL,
  secret VARCHAR(60) NOT NULL DEFAULT '',
  email VARCHAR(255),
  notes VARCHAR(100) DEFAULT '',
  otp VARCHAR(100) DEFAULT '',
  PRIMARY KEY (id)
);

CREATE TABLE yubikeys (
  id INT NOT NULL UNIQUE AUTO_INCREMENT,
  active BOOLEAN DEFAULT TRUE,
  created DATETIME NOT NULL,
  accessed DATETIME,
  publicName VARCHAR(16) UNIQUE NOT NULL COLLATE ascii_bin,
  internalName VARCHAR(12) NOT NULL COLLATE ascii_bin,
  counter INT,
  low INT,
  high INT,
  sessionUse INT,
  nonce VARCHAR(64) DEFAULT '',
  notes VARCHAR(100) DEFAULT '',
  PRIMARY KEY (id)
);

CREATE TABLE queue (
  id INT NOT NULL UNIQUE AUTO_INCREMENT,
  queued_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  modified_time TIMESTAMP,
  random_key INT,
  otp VARCHAR(100) NOT NULL,
  server VARCHAR(100) NOT NULL,
  info VARCHAR(256) NOT NULL,
  PRIMARY KEY (id)
);

-- DROP USER 'ykval_verifier'@'localhost';
CREATE USER 'ykval_verifier'@'localhost';
GRANT SELECT,INSERT,UPDATE(accessed, counter, low, high, sessionUse, nonce)
       ON ykval.yubikeys to 'ykval_verifier'@'localhost';
GRANT SELECT(id, secret, active)
       ON ykval.clients to 'ykval_verifier'@'localhost';
GRANT SELECT,INSERT,UPDATE,DELETE 
	ON ykval.queue to 'ykval_verifier'@'localhost';

-- DROP USER 'ykval_revoke'@'localhost';
CREATE USER 'ykval_revoke'@'localhost';
GRANT UPDATE(active)
        ON ykval.yubikeys to 'ykval_revoke'@'localhost';
GRANT SELECT(publicName)
        ON ykval.yubikeys to 'ykval_revoke'@'localhost';

FLUSH PRIVILEGES;
