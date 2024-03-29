CREATE OR REPLACE DIRECTORY image_dir AS '/home/zabbix/zabbix/create/output_png'
/

CREATE OR REPLACE PROCEDURE LOAD_IMAGE (IMG_ID IN NUMBER, IMG_TYPE IN NUMBER, IMG_NAME IN VARCHAR2, FILE_NAME IN VARCHAR2)
IS
	TEMP_BLOB BLOB := EMPTY_BLOB();
	BFILE_LOC BFILE;
BEGIN
	DBMS_LOB.CREATETEMPORARY(TEMP_BLOB,TRUE,DBMS_LOB.SESSION);
	BFILE_LOC := BFILENAME('IMAGE_DIR', FILE_NAME);
	DBMS_LOB.FILEOPEN(BFILE_LOC);
	DBMS_LOB.LOADFROMFILE(TEMP_BLOB, BFILE_LOC, DBMS_LOB.GETLENGTH(BFILE_LOC));
	DBMS_LOB.FILECLOSE(BFILE_LOC);
	INSERT INTO IMAGES VALUES (IMG_ID, IMG_TYPE, IMG_NAME, TEMP_BLOB);
	COMMIT;
END LOAD_IMAGE;
/

BEGIN
	LOAD_IMAGE(1,1,'Cloud','png/Cloud (128).png');
	LOAD_IMAGE(2,1,'Cloud','png/Cloud (24).png');
	LOAD_IMAGE(3,1,'Cloud','png/Cloud (48).png');
	LOAD_IMAGE(4,1,'Cloud','png/Cloud (64).png');
	LOAD_IMAGE(5,1,'Cloud','png/Cloud (96).png');
	LOAD_IMAGE(6,1,'Crypto-router','png/Crypto-router (128).png');
	LOAD_IMAGE(7,1,'Crypto-router','png/Crypto-router (24).png');
	LOAD_IMAGE(8,1,'Crypto-router','png/Crypto-router (48).png');
	LOAD_IMAGE(9,1,'Crypto-router','png/Crypto-router (64).png');
	LOAD_IMAGE(10,1,'Crypto-router','png/Crypto-router (96).png');
	LOAD_IMAGE(11,1,'Crypto-router_symbol','png/Crypto-router_symbol (128).png');
	LOAD_IMAGE(12,1,'Crypto-router_symbol','png/Crypto-router_symbol (24).png');
	LOAD_IMAGE(13,1,'Crypto-router_symbol','png/Crypto-router_symbol (48).png');
	LOAD_IMAGE(14,1,'Crypto-router_symbol','png/Crypto-router_symbol (64).png');
	LOAD_IMAGE(15,1,'Crypto-router_symbol','png/Crypto-router_symbol (96).png');
	LOAD_IMAGE(16,1,'Disk_array_2D','png/Disk_array_2D (128).png');
	LOAD_IMAGE(17,1,'Disk_array_2D','png/Disk_array_2D (24).png');
	LOAD_IMAGE(18,1,'Disk_array_2D','png/Disk_array_2D (48).png');
	LOAD_IMAGE(19,1,'Disk_array_2D','png/Disk_array_2D (64).png');
	LOAD_IMAGE(20,1,'Disk_array_2D','png/Disk_array_2D (96).png');
	LOAD_IMAGE(21,1,'Disk_array_3D','png/Disk_array_3D (128).png');
	LOAD_IMAGE(22,1,'Disk_array_3D','png/Disk_array_3D (24).png');
	LOAD_IMAGE(23,1,'Disk_array_3D','png/Disk_array_3D (48).png');
	LOAD_IMAGE(24,1,'Disk_array_3D','png/Disk_array_3D (64).png');
	LOAD_IMAGE(25,1,'Disk_array_3D','png/Disk_array_3D (96).png');
	LOAD_IMAGE(26,1,'Firewall','png/Firewall (128).png');
	LOAD_IMAGE(27,1,'Firewall','png/Firewall (24).png');
	LOAD_IMAGE(28,1,'Firewall','png/Firewall (48).png');
	LOAD_IMAGE(29,1,'Firewall','png/Firewall (64).png');
	LOAD_IMAGE(30,1,'Firewall','png/Firewall (96).png');
	LOAD_IMAGE(31,1,'House','png/House (128).png');
	LOAD_IMAGE(32,1,'House','png/House (24).png');
	LOAD_IMAGE(33,1,'House','png/House (48).png');
	LOAD_IMAGE(34,1,'House','png/House (64).png');
	LOAD_IMAGE(35,1,'House','png/House (96).png');
	LOAD_IMAGE(36,1,'Hub','png/Hub (128).png');
	LOAD_IMAGE(37,1,'Hub','png/Hub (24).png');
	LOAD_IMAGE(38,1,'Hub','png/Hub (48).png');
	LOAD_IMAGE(39,1,'Hub','png/Hub (64).png');
	LOAD_IMAGE(40,1,'Hub','png/Hub (96).png');
	LOAD_IMAGE(41,1,'IP_PBX','png/IP_PBX (128).png');
	LOAD_IMAGE(42,1,'IP_PBX','png/IP_PBX (24).png');
	LOAD_IMAGE(43,1,'IP_PBX','png/IP_PBX (48).png');
	LOAD_IMAGE(44,1,'IP_PBX','png/IP_PBX (64).png');
	LOAD_IMAGE(45,1,'IP_PBX','png/IP_PBX (96).png');
	LOAD_IMAGE(46,1,'IP_PBX_symbol','png/IP_PBX_symbol (128).png');
	LOAD_IMAGE(47,1,'IP_PBX_symbol','png/IP_PBX_symbol (24).png');
	LOAD_IMAGE(48,1,'IP_PBX_symbol','png/IP_PBX_symbol (48).png');
	LOAD_IMAGE(49,1,'IP_PBX_symbol','png/IP_PBX_symbol (64).png');
	LOAD_IMAGE(50,1,'IP_PBX_symbol','png/IP_PBX_symbol (96).png');
	LOAD_IMAGE(51,1,'Modem','png/Modem (128).png');
	LOAD_IMAGE(52,1,'Modem','png/Modem (24).png');
	LOAD_IMAGE(53,1,'Modem','png/Modem (48).png');
	LOAD_IMAGE(54,1,'Modem','png/Modem (64).png');
	LOAD_IMAGE(55,1,'Modem','png/Modem (96).png');
	LOAD_IMAGE(56,1,'Network','png/Network (128).png');
	LOAD_IMAGE(57,1,'Network','png/Network (24).png');
	LOAD_IMAGE(58,1,'Network','png/Network (48).png');
	LOAD_IMAGE(59,1,'Network','png/Network (64).png');
	LOAD_IMAGE(60,1,'Network','png/Network (96).png');
	LOAD_IMAGE(61,1,'Network_adapter','png/Network_adapter (128).png');
	LOAD_IMAGE(62,1,'Network_adapter','png/Network_adapter (24).png');
	LOAD_IMAGE(63,1,'Network_adapter','png/Network_adapter (48).png');
	LOAD_IMAGE(64,1,'Network_adapter','png/Network_adapter (64).png');
	LOAD_IMAGE(65,1,'Network_adapter','png/Network_adapter (96).png');
	LOAD_IMAGE(66,1,'Notebook','png/Notebook (128).png');
	LOAD_IMAGE(67,1,'Notebook','png/Notebook (24).png');
	LOAD_IMAGE(68,1,'Notebook','png/Notebook (48).png');
	LOAD_IMAGE(69,1,'Notebook','png/Notebook (64).png');
	LOAD_IMAGE(70,1,'Notebook','png/Notebook (96).png');
	LOAD_IMAGE(71,1,'PBX','png/PBX (128).png');
	LOAD_IMAGE(72,1,'PBX','png/PBX (24).png');
	LOAD_IMAGE(73,1,'PBX','png/PBX (48).png');
	LOAD_IMAGE(74,1,'PBX','png/PBX (64).png');
	LOAD_IMAGE(75,1,'PBX','png/PBX (96).png');
	LOAD_IMAGE(76,1,'Phone','png/Phone (128).png');
	LOAD_IMAGE(77,1,'Phone','png/Phone (24).png');
	LOAD_IMAGE(78,1,'Phone','png/Phone (48).png');
	LOAD_IMAGE(79,1,'Phone','png/Phone (64).png');
	LOAD_IMAGE(80,1,'Phone','png/Phone (96).png');
	LOAD_IMAGE(81,1,'Printer','png/Printer (128).png');
	LOAD_IMAGE(82,1,'Printer','png/Printer (24).png');
	LOAD_IMAGE(83,1,'Printer','png/Printer (48).png');
	LOAD_IMAGE(84,1,'Printer','png/Printer (64).png');
	LOAD_IMAGE(85,1,'Printer','png/Printer (96).png');
	LOAD_IMAGE(86,1,'Rack_42','png/Rack_42 (128).png');
	LOAD_IMAGE(87,1,'Rack_42','png/Rack_42 (64).png');
	LOAD_IMAGE(88,1,'Rack_42','png/Rack_42 (96).png');
	LOAD_IMAGE(89,1,'Rack_42_with_door','png/Rack_42_with_door (128).png');
	LOAD_IMAGE(90,1,'Rack_42_with_door','png/Rack_42_with_door (64).png');
	LOAD_IMAGE(91,1,'Rack_42_with_door','png/Rack_42_with_door (96).png');
	LOAD_IMAGE(92,1,'Rackmountable_1U_server_2D','png/Rackmountable_1U_server_2D (128).png');
	LOAD_IMAGE(93,1,'Rackmountable_1U_server_2D','png/Rackmountable_1U_server_2D (64).png');
	LOAD_IMAGE(94,1,'Rackmountable_1U_server_2D','png/Rackmountable_1U_server_2D (96).png');
	LOAD_IMAGE(95,1,'Rackmountable_1U_server_3D','png/Rackmountable_1U_server_3D (128).png');
	LOAD_IMAGE(96,1,'Rackmountable_1U_server_3D','png/Rackmountable_1U_server_3D (64).png');
	LOAD_IMAGE(97,1,'Rackmountable_1U_server_3D','png/Rackmountable_1U_server_3D (96).png');
	LOAD_IMAGE(98,1,'Rackmountable_2U_server_2D','png/Rackmountable_2U_server_2D (128).png');
	LOAD_IMAGE(99,1,'Rackmountable_2U_server_2D','png/Rackmountable_2U_server_2D (64).png');
	LOAD_IMAGE(100,1,'Rackmountable_2U_server_2D','png/Rackmountable_2U_server_2D (96).png');
	LOAD_IMAGE(101,1,'Rackmountable_2U_server_3D','png/Rackmountable_2U_server_3D (128).png');
	LOAD_IMAGE(102,1,'Rackmountable_2U_server_3D','png/Rackmountable_2U_server_3D (64).png');
	LOAD_IMAGE(103,1,'Rackmountable_2U_server_3D','png/Rackmountable_2U_server_3D (96).png');
	LOAD_IMAGE(104,1,'Rackmountable_3U_server_2D','png/Rackmountable_3U_server_2D (128).png');
	LOAD_IMAGE(105,1,'Rackmountable_3U_server_2D','png/Rackmountable_3U_server_2D (64).png');
	LOAD_IMAGE(106,1,'Rackmountable_3U_server_2D','png/Rackmountable_3U_server_2D (96).png');
	LOAD_IMAGE(107,1,'Rackmountable_3U_server_3D','png/Rackmountable_3U_server_3D (128).png');
	LOAD_IMAGE(108,1,'Rackmountable_3U_server_3D','png/Rackmountable_3U_server_3D (64).png');
	LOAD_IMAGE(109,1,'Rackmountable_3U_server_3D','png/Rackmountable_3U_server_3D (96).png');
	LOAD_IMAGE(110,1,'Rackmountable_4U_server_2D','png/Rackmountable_4U_server_2D (128).png');
	LOAD_IMAGE(111,1,'Rackmountable_4U_server_2D','png/Rackmountable_4U_server_2D (64).png');
	LOAD_IMAGE(112,1,'Rackmountable_4U_server_2D','png/Rackmountable_4U_server_2D (96).png');
	LOAD_IMAGE(113,1,'Rackmountable_4U_server_3D','png/Rackmountable_4U_server_3D (128).png');
	LOAD_IMAGE(114,1,'Rackmountable_4U_server_3D','png/Rackmountable_4U_server_3D (64).png');
	LOAD_IMAGE(115,1,'Rackmountable_4U_server_3D','png/Rackmountable_4U_server_3D (96).png');
	LOAD_IMAGE(116,1,'Rackmountable_5U_server_2D','png/Rackmountable_5U_server_2D (128).png');
	LOAD_IMAGE(117,1,'Rackmountable_5U_server_2D','png/Rackmountable_5U_server_2D (64).png');
	LOAD_IMAGE(118,1,'Rackmountable_5U_server_2D','png/Rackmountable_5U_server_2D (96).png');
	LOAD_IMAGE(119,1,'Rackmountable_5U_server_3D','png/Rackmountable_5U_server_3D (128).png');
	LOAD_IMAGE(120,1,'Rackmountable_5U_server_3D','png/Rackmountable_5U_server_3D (64).png');
	LOAD_IMAGE(121,1,'Rackmountable_5U_server_3D','png/Rackmountable_5U_server_3D (96).png');
	LOAD_IMAGE(122,1,'Router','png/Router (128).png');
	LOAD_IMAGE(123,1,'Router','png/Router (24).png');
	LOAD_IMAGE(124,1,'Router','png/Router (48).png');
	LOAD_IMAGE(125,1,'Router','png/Router (64).png');
	LOAD_IMAGE(126,1,'Router','png/Router (96).png');
	LOAD_IMAGE(127,1,'Router_symbol','png/Router_symbol (128).png');
	LOAD_IMAGE(128,1,'Router_symbol','png/Router_symbol (24).png');
	LOAD_IMAGE(129,1,'Router_symbol','png/Router_symbol (48).png');
	LOAD_IMAGE(130,1,'Router_symbol','png/Router_symbol (64).png');
	LOAD_IMAGE(131,1,'Router_symbol','png/Router_symbol (96).png');
	LOAD_IMAGE(132,1,'SAN','png/SAN (128).png');
	LOAD_IMAGE(133,1,'SAN','png/SAN (24).png');
	LOAD_IMAGE(134,1,'SAN','png/SAN (48).png');
	LOAD_IMAGE(135,1,'SAN','png/SAN (64).png');
	LOAD_IMAGE(136,1,'SAN','png/SAN (96).png');
	LOAD_IMAGE(137,1,'Satellite','png/Satellite (128).png');
	LOAD_IMAGE(138,1,'Satellite','png/Satellite (24).png');
	LOAD_IMAGE(139,1,'Satellite','png/Satellite (48).png');
	LOAD_IMAGE(140,1,'Satellite','png/Satellite (64).png');
	LOAD_IMAGE(141,1,'Satellite','png/Satellite (96).png');
	LOAD_IMAGE(142,1,'Satellite_antenna','png/Satellite_antenna (128).png');
	LOAD_IMAGE(143,1,'Satellite_antenna','png/Satellite_antenna (24).png');
	LOAD_IMAGE(144,1,'Satellite_antenna','png/Satellite_antenna (48).png');
	LOAD_IMAGE(145,1,'Satellite_antenna','png/Satellite_antenna (64).png');
	LOAD_IMAGE(146,1,'Satellite_antenna','png/Satellite_antenna (96).png');
	LOAD_IMAGE(147,1,'Server','png/Server (128).png');
	LOAD_IMAGE(148,1,'Server','png/Server (24).png');
	LOAD_IMAGE(149,1,'Server','png/Server (48).png');
	LOAD_IMAGE(150,1,'Server','png/Server (64).png');
	LOAD_IMAGE(151,1,'Server','png/Server (96).png');
	LOAD_IMAGE(152,1,'Switch','png/Switch (128).png');
	LOAD_IMAGE(153,1,'Switch','png/Switch (24).png');
	LOAD_IMAGE(154,1,'Switch','png/Switch (48).png');
	LOAD_IMAGE(155,1,'Switch','png/Switch (64).png');
	LOAD_IMAGE(156,1,'Switch','png/Switch (96).png');
	LOAD_IMAGE(157,1,'UPS','png/UPS (128).png');
	LOAD_IMAGE(158,1,'UPS','png/UPS (24).png');
	LOAD_IMAGE(159,1,'UPS','png/UPS (48).png');
	LOAD_IMAGE(160,1,'UPS','png/UPS (64).png');
	LOAD_IMAGE(161,1,'UPS','png/UPS (96).png');
	LOAD_IMAGE(162,1,'UPS_rackmountable_2D','png/UPS_rackmountable_2D (128).png');
	LOAD_IMAGE(163,1,'UPS_rackmountable_2D','png/UPS_rackmountable_2D (24).png');
	LOAD_IMAGE(164,1,'UPS_rackmountable_2D','png/UPS_rackmountable_2D (48).png');
	LOAD_IMAGE(165,1,'UPS_rackmountable_2D','png/UPS_rackmountable_2D (64).png');
	LOAD_IMAGE(166,1,'UPS_rackmountable_2D','png/UPS_rackmountable_2D (96).png');
	LOAD_IMAGE(167,1,'UPS_rackmountable_3D','png/UPS_rackmountable_3D (128).png');
	LOAD_IMAGE(168,1,'UPS_rackmountable_3D','png/UPS_rackmountable_3D (24).png');
	LOAD_IMAGE(169,1,'UPS_rackmountable_3D','png/UPS_rackmountable_3D (48).png');
	LOAD_IMAGE(170,1,'UPS_rackmountable_3D','png/UPS_rackmountable_3D (64).png');
	LOAD_IMAGE(171,1,'UPS_rackmountable_3D','png/UPS_rackmountable_3D (96).png');
	LOAD_IMAGE(172,1,'Video_terminal','png/Video_terminal (128).png');
	LOAD_IMAGE(173,1,'Video_terminal','png/Video_terminal (24).png');
	LOAD_IMAGE(174,1,'Video_terminal','png/Video_terminal (48).png');
	LOAD_IMAGE(175,1,'Video_terminal','png/Video_terminal (64).png');
	LOAD_IMAGE(176,1,'Video_terminal','png/Video_terminal (96).png');
	LOAD_IMAGE(177,1,'Workstation','png/Workstation (128).png');
	LOAD_IMAGE(178,1,'Workstation','png/Workstation (24).png');
	LOAD_IMAGE(179,1,'Workstation','png/Workstation (48).png');
	LOAD_IMAGE(180,1,'Workstation','png/Workstation (64).png');
	LOAD_IMAGE(181,1,'Workstation','png/Workstation (96).png');
	LOAD_IMAGE(182,1,'Zabbix_server_2D','png/Zabbix_server_2D (128).png');
	LOAD_IMAGE(183,1,'Zabbix_server_2D','png/Zabbix_server_2D (64).png');
	LOAD_IMAGE(184,1,'Zabbix_server_2D','png/Zabbix_server_2D (96).png');
	LOAD_IMAGE(185,1,'Zabbix_server_3D','png/Zabbix_server_3D (128).png');
	LOAD_IMAGE(186,1,'Zabbix_server_3D','png/Zabbix_server_3D (64).png');
	LOAD_IMAGE(187,1,'Zabbix_server_3D','png/Zabbix_server_3D (96).png');
END;
/

DROP PROCEDURE LOAD_IMAGE;

DROP DIRECTORY image_dir;
