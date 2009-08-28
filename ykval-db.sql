-- DROP DATABASE ykval;
CREATE DATABASE ykval;
USE ykval;

CREATE TABLE clients (
  id INT NOT NULL AUTO_INCREMENT,
  active BOOLEAN DEFAULT TRUE,
  created DATETIME NOT NULL,
  secret VARCHAR(60) NOT NULL DEFAULT '',
  email VARCHAR(255),
  otp VARCHAR(100) DEFAULT '',
  notes VARCHAR(100) DEFAULT '',
  PRIMARY KEY (id)
);

CREATE TABLE yubikeys (
  id INT NOT NULL UNIQUE AUTO_INCREMENT,
  active BOOLEAN DEFAULT TRUE,
  created DATETIME NOT NULL,
  accessed DATETIME,
  publicName VARCHAR(16) UNIQUE NOT NULL,
  internalName VARCHAR(12) NOT NULL,
  counter INT,
  low INT,
  high INT,
  sessionUse INT,
  notes VARCHAR(100) DEFAULT '',
  PRIMARY KEY (id)
);

-- DROP USER 'ykval_verifier'@'localhost';
CREATE USER 'ykval_verifier'@'localhost';
GRANT SELECT,INSERT,UPDATE(accessed, counter, low, high, sessionUse)
       ON ykval.yubikeys to 'ykval_verifier'@'localhost';
GRANT SELECT(id, secret, active)
       ON ykval.clients to 'ykval_verifier'@'localhost';

-- DROP USER 'ykval_getapikey'@'localhost';
CREATE USER 'ykval_getapikey'@'localhost';
GRANT SELECT(id),INSERT
	ON ykval.clients to 'ykval_getapikey'@'localhost';

FLUSH PRIVILEGES;
