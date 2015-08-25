SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;


CREATE TABLE IF NOT EXISTS `compos` (
  `idcompo` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(64) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `active` tinyint(4) unsigned NOT NULL DEFAULT '1',
  `idhost` int(10) unsigned NOT NULL,
  `start_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `downloadable` tinyint(3) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`idcompo`),
  KEY `idhost` (`idhost`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=633 ;

CREATE TABLE IF NOT EXISTS `entries` (
  `identry` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `author` varchar(64) NOT NULL,
  `filename` varchar(64) NOT NULL,
  `title` varchar(32) NOT NULL,
  `date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `idcompo` int(10) unsigned NOT NULL,
  `altered` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `points` int(11) DEFAULT NULL,
  `place` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`identry`),
  KEY `idcompo` (`idcompo`),
  KEY `points` (`points`),
  KEY `place` (`place`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=5223 ;

CREATE TABLE IF NOT EXISTS `hosts` (
  `idhost` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `hostname` varchar(64) NOT NULL,
  `password` varchar(328) NOT NULL,
  `access_level` tinyint(4) unsigned NOT NULL,
  PRIMARY KEY (`idhost`),
  UNIQUE KEY `hostname_UNIQUE` (`hostname`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=32 ;

CREATE TABLE IF NOT EXISTS `uploading` (
  `idupload` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `author` varchar(64) NOT NULL,
  `start` int(10) unsigned NOT NULL,
  `idcompo` int(10) unsigned NOT NULL,
  PRIMARY KEY (`idupload`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=4745 ;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
