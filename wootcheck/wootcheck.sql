-- 
-- Table structure for table `wootcheck`
-- 

CREATE TABLE `wootcheck` (
  `uid` int(10) unsigned NOT NULL auto_increment,
  `id` int(5) NOT NULL,
  `message` varchar(128) NOT NULL,
  `product` text NOT NULL,
  `lastupdate` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
  `wantone` text NOT NULL,
  `price` varchar(12) NOT NULL,
  `timeleft` int(11) NOT NULL,
  PRIMARY KEY  (`uid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 ;

