SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;


CREATE TABLE `compos` (
  `idcompo` int(10) UNSIGNED NOT NULL,
  `name` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(4) UNSIGNED NOT NULL DEFAULT 1,
  `idhost` int(10) UNSIGNED NOT NULL,
  `start_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `downloadable` tinyint(3) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE `entries` (
  `identry` int(10) UNSIGNED NOT NULL,
  `author` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `filename` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `date` timestamp NOT NULL DEFAULT current_timestamp(),
  `idcompo` int(10) UNSIGNED NOT NULL,
  `altered` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `points` int(11) DEFAULT NULL,
  `place` int(10) UNSIGNED DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE `hosts` (
  `idhost` int(10) UNSIGNED NOT NULL,
  `hostname` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(328) NOT NULL,
  `access_level` tinyint(4) UNSIGNED NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE `uploading` (
  `idupload` int(10) UNSIGNED NOT NULL,
  `author` varchar(64) NOT NULL,
  `start` int(10) UNSIGNED NOT NULL,
  `idcompo` int(10) UNSIGNED NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;


ALTER TABLE `compos`
  ADD PRIMARY KEY (`idcompo`),
  ADD KEY `idhost` (`idhost`);

ALTER TABLE `entries`
  ADD PRIMARY KEY (`identry`),
  ADD KEY `idcompo` (`idcompo`),
  ADD KEY `points` (`points`),
  ADD KEY `place` (`place`);

ALTER TABLE `hosts`
  ADD PRIMARY KEY (`idhost`),
  ADD UNIQUE KEY `hostname_UNIQUE` (`hostname`);

ALTER TABLE `uploading`
  ADD PRIMARY KEY (`idupload`);


ALTER TABLE `compos`
  MODIFY `idcompo` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `entries`
  MODIFY `identry` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `hosts`
  MODIFY `idhost` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `uploading`
  MODIFY `idupload` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
