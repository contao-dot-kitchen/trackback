<?php

ClassLoader::addNamespaces(array
(
	'Contao'
));

ClassLoader::addClasses(array
(
	'Contao\XMLRPCServer' => 'system/modules/trackback/xmlrpc/XMLRPCServer.php'
));

TemplateLoader::addFiles(array());