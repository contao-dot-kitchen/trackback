Request-Testing
===============

* Edit assets/xml/request.xml
* Put your Source Url with a Link to > http://contao.kitchen/de/beitrag/einen-weblog-mit-contao-erstellen.html in your HTML Markup
* Change Directory to assets/xml/
* Execute the following Command in bash:
* curl -X POST -d @request.xml http://contao.kitchen/system/modules/news_pingback/xmlrpc/XMLRPCServer.php