Roadmap
=======

* Snipped-Generator
* tl_settings.php

Copyright
=========

Sascha Brandhoff, Mühlweg 3a, 35260 Stadtallendorf, https://contao.kitchen

Request-Testing
===============

* Bearbeite die Datei assets/xml/request.xml
* Füge dein Source-Url ein. Auf deiner Source-Url sollte im HTML Markup nachfolgende Url stehen: https://contao.kitchen/de/beitrag/einen-weblog-mit-contao-erstellen.html in your HTML Markup
* Wechsel in das Verzeichnis assets/xml/
* Führe den nachfolgenden Befehl in der Kommandozeile aus (Achtung: Linux):
* curl -X POST -d @request.xml https://contao.kitchen/system/modules/news_pingback/xmlrpc/XMLRPCServer.php

Danke
=====

An dieser Stelle ein besonderer Dank an Dirk Weimar der das ursprüngliche Module bereitgestellt hat. Seine Webseite findet Ihr hier: http://www.selected-stuff.de
