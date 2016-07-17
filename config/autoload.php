<?php

ClassLoader::addNamespaces(array
(
	'Contao'
));

ClassLoader::addClasses(array
(
	'Contao\XMLRPCServer' => 'system/modules/news_pingback/xmlrpc/XMLRPCServer.php'
));

TemplateLoader::addFiles(array());