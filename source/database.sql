SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;


CREATE TABLE IF NOT EXISTS `pwl-cache` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `test_list` int(2) NOT NULL DEFAULT '0',
  `timestamp` int(10) NOT NULL,
  `content` text NOT NULL,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `timestamp` (`timestamp`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=143 ;

CREATE TABLE IF NOT EXISTS `pwl-cache-v2apps` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(10) NOT NULL,
  `timestamp` int(10) NOT NULL,
  `content` text NOT NULL,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `timestamp` (`timestamp`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=61 ;

CREATE TABLE IF NOT EXISTS `pwl-custom_apps` (
  `ID` int(4) NOT NULL AUTO_INCREMENT,
  `name` text NOT NULL,
  `v2app` int(2) NOT NULL DEFAULT '0',
  `test_app` int(2) NOT NULL DEFAULT '0',
  `content` longtext NOT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=14 ;

INSERT INTO `pwl-custom_apps` (`ID`, `name`, `v2app`, `test_app`, `content`) VALUES
(1, 'Fling', 0, 0, '{"use_channel":true,"allow_empty_post_data":true,"app_id":"Fling","url":"${POST_DATA}","dial_enabled":true}'),
(2, 'TeamEureka-Idlescreen-Dev', 0, 1, '{"use_channel":true,"allow_empty_post_data":true,"app_id":"TeamEureka-Idlescreen-Dev","url":"chrome://home?remote_url=http://pdl.team-eureka.com/dev/idle/","dial_enabled":true}'),
(3, '674A0243', 1, 0, '{"display_name":"Android Mirroring","uses_ipc":true,"external":true,"native_app":true,"app_id":"674A0243","command_line":"/bin/logwrapper /chrome/v2mirroring --vmodule\\u003d*media/cast/*\\u003d1,*\\u003d0 ${POST_DATA}"}');


/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
