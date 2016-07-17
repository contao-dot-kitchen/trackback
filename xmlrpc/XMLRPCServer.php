<?php 

$arrPost = $_POST;
unset($_POST);

define('TL_MODE', 'FE');
require('../../../initialize.php');

$_POST = $arrPost;

class XMLRPCServer extends Frontend
{
	protected $errorCode = '';
	protected $moderateComments = true;
	protected $faultCodes = array
	(
		'0'  => 'Unknown error',
		'16' => 'The source URI does not exist.',
		'17' => 'The source URI does not contain a link to the target URI, and so cannot be used as a source.',
		'32' => 'The specified target URI does not exist.',
		'33' => 'The specified target URI cannot be used as a target. It either doesn´t exist, or it is not a  pingback-enabled resource.',
		'48' => 'The pingback has already been registered.',
		'49' => 'Access denied.',
		'50' => 'The server could not communicate with an upstream server, or received an error from an upstream server, and therefore could not complete the request.',
		'99' => 'Pingbacks are not allowed for the specified target.'
	);

	public function __construct()
	{
		parent::__construct();
	}

	public function run()
	{
		$strData = file_get_contents('php://input');
		if(!$strData)
		{
			return;
		}

		$objData = simplexml_load_string($strData);
		if('pingback.ping' === (string) $objData->methodName and isset($objData->params->param[0]->value) and isset($objData->params->param[1]->value))
		{
			$strSource = trim($objData->params->param[0]->value->string);
			$strTarget = trim($objData->params->param[1]->value->string);
		}
		else
		{
			return;
		}

		$strNewsAliasOrId = str_replace('.html','',end(explode('/', $strTarget)));
		$objNews = \NewsModel::findByAlias($strNewsAliasOrId);
		$arrNewsUrls = $this->generateNewsUrls();

		if(!in_array($strTarget, $arrNewsUrls) && !in_array(str_replace('http://', 'http://www.', $strTarget), $arrNewsUrls) and !in_array(str_replace('http://www.', 'http://', $strTarget), $arrNewsUrls))
		{
			$this->errorCode = '33';
		}

		if(!$this->errorCode)
		{
			$objArchive = \NewsArchiveModel::findById($objNews->pid);

			if(!$objArchive->allowComments)
			{
				$this->errorCode = '99';
			}

			if(!$objArchive->moderate)
			{
				$this->moderateComments = false;
			} 
		}
	    
		if(!$this->errorCode)
		{
	    		$arrLinks = $this->getUrlsFromPage($strSource);
			if(!in_array($strTarget, $arrLinks) && !in_array(str_replace('http://', 'http://www.', $strTarget), $arrLinks) && !in_array(str_replace('http:www.//', 'http://', $strTarget), $arrLinks))
			{
				$this->errorCode = '17';		
			}
		}

		if(!$this->errorCode)
		{
			$objComment = $this->Database->prepare("SELECT id FROM tl_comments WHERE (source=? && parent=? && website=?)")->execute('tl_news', $objNews->id, $strSource);
			if($objComment->count())
			{
				$this->errorCode = '48';
			}
		}

		if(!$this->errorCode)
		{
			$arrComment = array
			(
				'tstamp'    => time(),
				'source'    => 'tl_news',
				'parent'    => $objNews->id,
				'date'      => time(),
				'name'      => 'PingPong',
				'email'     => 'ping@pong.net',
				'website'   => $strSource,
				'comment'   => 'Pingback from <a href="' . $strSource . '">' . $strSource . '</a> received.',
				'published' => ($this->moderateComments ? '' : 1)
			);

			$objComment = new \CommentsModel();
			$objComment->setRow($arrComment);
			$objComment->save();

			$objEmail           = new \Email();
			$objEmail->from     = \Config::Get('adminEmail');
			$objEmail->fromName = $GLOBALS['_SERVER']['SERVER_NAME'];
			$objEmail->subject  = 'Pingback received';
			$objEmail->text     = "Pingback from ". $strSource . " to " . $strTarget . " received.\n\nYou can edit and/or activate this pingback in the comments section in your backend.";
			$objEmail->sendTo(\Config::Get('adminEmail'));

			\System::log('[pingback] ' . $strText, __METHOD__, TL_GENERAL);

			$strResponse = $this->buildSuccessXML($strSource, $strTarget);
		}
		else
		{
			$strErrorMessage = $this->faultCodes[$this->errorCode];
			$strResponse = $this->buildErrorXML($this->errorCode, $strErrorMessage);
	        }

		echo $strResponse; 
	}

	protected function generateNewsUrls()
	{
		$arrNews   = array();
		$arrUrls   = array();

		$objResult = $this->Database->execute('SELECT id, pid, alias FROM tl_news');
		if($objResult->numRows)
		{
			$arrRows = $objResult->fetchAllAssoc();
			foreach($arrRows as $arrRow)
			{
				array_push($arrNews, array
				(
					'id'    => $arrRow['id'], 
					'pid'   => $arrRow['pid'], 
					'alias' => $arrRow['alias'])
				);
			}

			foreach($arrNews as $arrNewsEntry)
			{
				$objResult = \NewsArchiveModel::findById($arrNewsEntry['pid']);
				$objPage = \PageModel::findById($objResult->jumpTo);
				$strUrl = \Environment::Get('base') . ampersand(\Controller::generateFrontendUrl($objPage->row(), '/' . ((!$GLOBALS['TL_CONFIG']['disableAlias'] && strlen($arrNewsEntry['alias'])) ? $arrNewsEntry['alias'] : $arrNewsEntry['id'])));

				array_push($arrUrls, $strUrl);		
			}

			return $arrUrls;
		}
	}

	private function getUrlsFromPage($strUrl)
	{
		$objRequest = new Request();
		$objRequest->send($strUrl);

		if($objRequest->hasError())
		{
			return $objRequest->error;
		}

		$arrMatches = array();
		preg_match_all("/href\s*=\s*[\"|\'](.*?)[\"|\']/i", $objRequest->response, $arrMatches);

		return $arrMatches[1];
	}

	protected function buildErrorXML($strErrorCode, $strErrorMessage)
	{
		$xml  = "<?xml version='1.0'?>\n";
		$xml .= "<methodResponse>\n";
		$xml .= "    <fault>\n";
		$xml .= "        <value>\n";
		$xml .= "            <struct>\n";
		$xml .= "                <member>\n";
		$xml .= "                    <name>faultCode</name>\n";
		$xml .= "                    <value>\n";
		$xml .= "                        <int>" . $strErrorCode . "</int>\n";
		$xml .= "                    </value>\n";
		$xml .= "                </member>\n";
		$xml .= "                <member>\n";
		$xml .= "                    <name>faultString</name>\n";
		$xml .= "                    <value>\n";
		$xml .= "                        <string>" . $strErrorMessage . "</string>\n";
		$xml .= "                    </value>\n";
		$xml .= "                </member>\n";
		$xml .= "            </struct>\n";
		$xml .= "        </value>\n";
		$xml .= "    </fault>\n";
		$xml .= "</methodResponse>\n";

		return $xml;
	}

	protected function buildSuccessXML($strSource, $strTarget)
	{
		$xml  = "<?xml version='1.0'?>\n";
		$xml .= "<methodResponse>\n";
		$xml .= "    <params>\n";
		$xml .= "        <param>\n";
		$xml .= "            <value>\n";
		$xml .= "                <string>Pingback from " . $strSource . " to " . $strTarget . " received - keep the web talking :)</string>\n";
		$xml .= "            </value>\n";
		$xml .= "        </param>\n";
		$xml .= "    </params>\n";
		$xml .= "</methodResponse>\n";

		return $xml;
	}
}

$objServer = new XMLRPCServer();
$objServer->run();