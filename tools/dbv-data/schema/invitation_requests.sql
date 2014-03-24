CREATE TABLE `invitation_requests` (
  `requestId` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(64) NOT NULL,
  PRIMARY KEY (`requestId`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1