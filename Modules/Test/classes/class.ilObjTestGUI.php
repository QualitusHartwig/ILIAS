<?php
/* Copyright (c) 1998-2013 ILIAS open source, Extended GPL, see docs/LICENSE */

require_once './Modules/Test/exceptions/class.ilTestException.php';
require_once './Services/Object/classes/class.ilObjectGUI.php';
require_once './Modules/Test/classes/inc.AssessmentConstants.php';
require_once './Modules/Test/classes/class.ilObjAssessmentFolderGUI.php';
require_once './Modules/Test/classes/class.ilTestExpressPage.php';

/**
 * Class ilObjTestGUI
 *
 * @author		Helmut Schottmüller <helmut.schottmueller@mac.com>
 * @author		Björn Heyser <bheyser@databay.de>
 * @author		Maximilian Becker <mbecker@databay.de>
 * 
 * @version		$Id$
 *
 * @ilCtrl_Calls ilObjTestGUI: ilObjCourseGUI, ilMDEditorGUI, ilCertificateGUI, ilPermissionGUI
 * @ilCtrl_Calls ilObjTestGUI: ilTestOutputGUI, ilTestPlayerDynamicQuestionSetGUI, ilLearningProgressGUI
 * @ilCtrl_Calls ilObjTestGUI: ilTestEvaluationGUI, ilAssGenFeedbackPageGUI, ilAssSpecFeedbackPageGUI
 * @ilCtrl_Calls ilObjTestGUI: ilInfoScreenGUI, ilShopPurchaseGUI, ilObjectCopyGUI, ilTestScoringGUI
 * @ilCtrl_Calls ilObjTestGUI: ilRepositorySearchGUI, ilScoringAdjustmentGUI, ilTestExportGUI
 * @ilCtrl_Calls ilObjTestGUI: assMultipleChoiceGUI, assClozeTestGUI, assMatchingQuestionGUI
 * @ilCtrl_Calls ilObjTestGUI: assOrderingQuestionGUI, assImagemapQuestionGUI, assJavaAppletGUI
 * @ilCtrl_Calls ilObjTestGUI: assNumericGUI, assErrorTextGUI, ilTestScoringByQuestionsGUI
 * @ilCtrl_Calls ilObjTestGUI: assTextSubsetGUI, assOrderingHorizontalGUI, ilTestToplistGUI
 * @ilCtrl_Calls ilObjTestGUI: assSingleChoiceGUI, assFileUploadGUI, assTextQuestionGUI, assFlashQuestionGUI
 * @ilCtrl_Calls ilObjTestGUI: ilTestExpressPageObjectGUI, ilPageEditorGUI, ilAssQuestionPageGUI
 * @ilCtrl_Calls ilObjTestGUI: ilObjQuestionPoolGUI, ilEditClipboardGUI, ilObjTestSettingsGeneralGUI
 * @ilCtrl_Calls ilObjTestGUI: ilCommonActionDispatcherGUI, ilObjTestDynamicQuestionSetConfigGUI
 * @ilCtrl_Calls ilObjTestGUI: ilTestRandomQuestionSetConfigGUI
 * @ilCtrl_Calls ilObjTestGUI: ilAssQuestionHintsGUI, ilAssQuestionFeedbackEditingGUI
 *
 * @ingroup ModulesTest
 */
class ilObjTestGUI extends ilObjectGUI
{
	/** @var ilObjTest $object */
	public $object = null;

	/** @var ilTestQuestionSetConfigFactory $testQuestionSetConfigFactory Factory for question set config. */
	private $testQuestionSetConfigFactory = null;
	
	/** @var ilTestPlayerFactory $testPlayerFactory Factory for test player. */
	private $testPlayerFactory = null;
	
	/** @var ilTestSessionFactory $testSessionFactory Factory for test session. */
	private $testSessionFactory = null;
	
	/** @var ilTestSequenceFactory $testSequenceFactory Factory for test sequence. */
	private $testSequenceFactory = null;
	
	/**
	* Constructor
	* @access public
	*/
	function ilObjTestGUI()
	{
		global $lng, $ilCtrl, $ilDB, $ilPluginAdmin, $tree;
		$lng->loadLanguageModule("assessment");
		$this->type = "tst";
		$this->ctrl =& $ilCtrl;
		$this->ctrl->saveParameter($this, array("ref_id", "test_ref_id", "calling_test", "test_express_mode", "q_id"));
		$this->ilObjectGUI("",$_GET["ref_id"], true, false);
		// Added parameter if called from crs_objectives
		if((int) $_GET['crs_show_result'])
		{
			$this->ctrl->saveParameter($this,'crs_show_result',(int) $_GET['crs_show_result']);
		}

		if( $this->object instanceof ilObjTest )
		{
			require_once 'Modules/Test/classes/class.ilTestQuestionSetConfigFactory.php';
			$this->testQuestionSetConfigFactory = new ilTestQuestionSetConfigFactory($tree, $ilDB, $ilPluginAdmin, $this->object);
			
			require_once 'Modules/Test/classes/class.ilTestPlayerFactory.php';
			$this->testPlayerFactory = new ilTestPlayerFactory($this->object);

			require_once 'Modules/Test/classes/class.ilTestSessionFactory.php';
			$this->testSessionFactory = new ilTestSessionFactory($this->object);

			require_once 'Modules/Test/classes/class.ilTestSequenceFactory.php';
			$this->testSequenceFactory = new ilTestSequenceFactory($ilDB, $lng, $ilPluginAdmin, $this->object);
		}
	}

	/**
	* execute command
	*/
	function executeCommand()
	{
		global $ilAccess, $ilNavigationHistory, $ilCtrl, $ilErr, $tpl, $lng, $ilTabs, $ilPluginAdmin, $ilDB, $tree;

		if((!$ilAccess->checkAccess("read", "", $_GET["ref_id"])) && (!$ilAccess->checkAccess("visible", "", $_GET["ref_id"])))
		{
			global $ilias;
			$ilias->raiseError($this->lng->txt("permission_denied"), $ilias->error_obj->MESSAGE);
		}

		$cmd = $this->ctrl->getCmd("infoScreen");

		$cmdsDisabledDueToOfflineStatus = array(
			'resumePlayer', 'resumePlayer', 'outUserResultsOverview', 'outUserListOfAnswerPasses'
		);

		if(!$this->getCreationMode() && !$this->object->isOnline() && in_array($cmd, $cmdsDisabledDueToOfflineStatus))
		{
			$cmd = 'infoScreen';
		}

		$next_class = $this->ctrl->getNextClass($this);
		$this->ctrl->setReturn($this, "infoScreen");

		if(method_exists($this->object, "getTestStyleLocation")) $this->tpl->addCss($this->object->getTestStyleLocation("output"), "screen");

		// add entry to navigation history
		if(!$this->getCreationMode() &&
			$ilAccess->checkAccess("read", "", $_GET["ref_id"])
		)
		{
			$ilNavigationHistory->addItem($_GET["ref_id"],
				"ilias.php?baseClass=ilObjTestGUI&cmd=infoScreen&ref_id=" . $_GET["ref_id"], "tst");
		}

		if(!$this->getCreationMode())
		{
			if(IS_PAYMENT_ENABLED)
			{
				require_once 'Services/Payment/classes/class.ilPaymentObject.php';
				if(ilPaymentObject::_requiresPurchaseToAccess($this->object->getRefId(), $type = (isset($_GET['purchasetype']) ? $_GET['purchasetype'] : NULL)))
				{
					$this->setLocator();
					$this->tpl->getStandardTemplate();

					include_once 'Services/Payment/classes/class.ilShopPurchaseGUI.php';
					$pp  = new ilShopPurchaseGUI((int)$_GET['ref_id']);
					$ret = $this->ctrl->forwardCommand($pp);
					$this->tpl->show();
					exit();
				}
			}
		}

		// elba hack for storing question id for inserting new question after
		if($_REQUEST['prev_qid'])
		{
			global $___prev_question_id;
			$___prev_question_id = $_REQUEST['prev_qid'];
			$this->ctrl->setParameter($this, 'prev_qid', $_REQUEST['prev_qid']);
		}

		if( !$this->getCreationMode() && $this->testQuestionSetConfigFactory->getQuestionSetConfig()->areDepenciesBroken() )
		{
			if( !$this->isValidRequestOnBrokenQuestionSetDepencies($next_class, $cmd) )
			{
				$this->ctrl->redirectByClass('ilObjTestGUI', 'infoScreen');
			}
		}
		
		switch($next_class)
		{
			case 'iltestexportgui':
				if(!$ilAccess->checkAccess('write', '', $this->ref_id))
				{
					$ilErr->raiseError($this->lng->txt('permission_denied'), $ilErr->WARNING);
				}

				$this->prepareOutput();
				$this->addHeaderAction();
				require_once 'Modules/Test/classes/class.ilTestExportGUI.php';
				$ilCtrl->forwardCommand(new ilTestExportGUI($this));
				break;

			case "ilinfoscreengui":
				$this->prepareOutput();
				$this->addHeaderAction();
				$this->infoScreen(); // forwards command
				break;
			case 'ilmdeditorgui':
				if(!$ilAccess->checkAccess('write', '', $this->object->getRefId()))
				{
					$ilErr->raiseError($this->lng->txt('permission_denied'), $ilErr->WARNING);
				}

				$this->prepareOutput();
				$this->addHeaderAction();
				require_once 'Services/MetaData/classes/class.ilMDEditorGUI.php';
				$md_gui =& new ilMDEditorGUI($this->object->getId(), 0, $this->object->getType());
				$md_gui->addObserver($this->object, 'MDUpdateListener', 'General');

				$this->ctrl->forwardCommand($md_gui);
				break;
				
			case "iltestoutputgui":
				require_once "./Modules/Test/classes/class.ilTestOutputGUI.php";
				if(!$this->object->getKioskMode()) $this->prepareOutput();

				$output_gui =& new ilTestOutputGUI($this->object);
				$this->ctrl->forwardCommand($output_gui);
				break;

			case "iltestplayerdynamicquestionsetgui":
				require_once "./Modules/Test/classes/class.ilTestPlayerDynamicQuestionSetGUI.php";
				if (!$this->object->getKioskMode()) $this->prepareOutput();
				$output_gui =& new ilTestPlayerDynamicQuestionSetGUI($this->object);
				$this->ctrl->forwardCommand($output_gui);
				break;

			case "iltestevaluationgui":
				$this->prepareOutput();
				$this->addHeaderAction();
				include_once "./Modules/Test/classes/class.ilTestEvaluationGUI.php";
				$evaluation_gui =& new ilTestEvaluationGUI($this->object);
				$this->ctrl->forwardCommand($evaluation_gui);
				break;

			case "iltestservicegui":
				$this->prepareOutput();
				$this->addHeaderAction();
				require_once "./Modules/Test/classes/class.ilTestServiceGUI.php";
				$serviceGUI =& new ilTestServiceGUI($this->object);
				$this->ctrl->forwardCommand($serviceGUI);
				break;

			case 'ilpermissiongui':
				$this->prepareOutput();
				$this->addHeaderAction();
				include_once("Services/AccessControl/classes/class.ilPermissionGUI.php");
				$perm_gui =& new ilPermissionGUI($this);
				$ret      =& $this->ctrl->forwardCommand($perm_gui);
				break;

			case "illearningprogressgui":
				$this->prepareOutput();
				$this->addHeaderAction();
				require_once './Services/Tracking/classes/class.ilLearningProgressGUI.php';
				$new_gui =& new ilLearningProgressGUI(ilLearningProgressGUI::LP_CONTEXT_REPOSITORY, $this->object->getRefId());
				$this->ctrl->forwardCommand($new_gui);

				break;

			case "ilcertificategui":
				$this->prepareOutput();
				$this->addHeaderAction();
				require_once "./Services/Certificate/classes/class.ilCertificateGUI.php";
				require_once "./Modules/Test/classes/class.ilTestCertificateAdapter.php";
				$output_gui = new ilCertificateGUI(new ilTestCertificateAdapter($this->object));
				$this->ctrl->forwardCommand($output_gui);
				break;

			case "iltestscoringgui":
				$this->prepareOutput();
				$this->addHeaderAction();
				require_once "./Modules/Test/classes/class.ilTestScoringGUI.php";
				$output_gui = new ilTestScoringGUI($this->object);
				$this->ctrl->forwardCommand($output_gui);
				break;

			case 'iltestscoringbyquestionsgui':
				$this->prepareOutput();
				$this->addHeaderAction();
				include_once 'Modules/Test/classes/class.ilTestScoringByQuestionsGUI.php';
				$output_gui = new ilTestScoringByQuestionsGUI($this->object);
				$this->ctrl->forwardCommand($output_gui);
				break;
			
			case 'ilobjtestsettingsgeneralgui':
				$this->prepareOutput();
				$this->addHeaderAction();
				require_once 'Modules/Test/classes/class.ilObjTestSettingsGeneralGUI.php';
				$gui = new ilObjTestSettingsGeneralGUI(
						$this->ctrl, $ilAccess, $this->lng, $this->tpl, $this->tree, $ilDB, $ilPluginAdmin, $this
				);
				$this->ctrl->forwardCommand($gui);
				break;
			
			case 'ilobjtestdynamicquestionsetconfiggui':
				$this->prepareOutput();
				$this->addHeaderAction();
				require_once 'Modules/Test/classes/class.ilObjTestDynamicQuestionSetConfigGUI.php';
				$gui = new ilObjTestDynamicQuestionSetConfigGUI($this->ctrl, $ilAccess, $ilTabs, $this->lng, $this->tpl, $ilDB, $tree, $this->object);
				$this->ctrl->forwardCommand($gui);
				break;
			
			case 'iltestrandomquestionsetconfiggui':
				$this->prepareOutput();
				$this->addHeaderAction();
				require_once 'Modules/Test/classes/class.ilTestRandomQuestionSetConfigGUI.php';
				$gui = new ilTestRandomQuestionSetConfigGUI($this->ctrl, $ilAccess, $ilTabs, $this->lng, $this->tpl, $ilDB, $tree, $ilPluginAdmin, $this->object);
				$this->ctrl->forwardCommand($gui);
				break;
				
			case 'ilobjectcopygui':
				$this->prepareOutput();
				$this->addHeaderAction();
				require_once './Services/Object/classes/class.ilObjectCopyGUI.php';
				$cp = new ilObjectCopyGUI($this);
				$cp->setType('tst');
				$this->ctrl->forwardCommand($cp);
				break;

			case 'ilrepositorysearchgui':
				$this->prepareOutput();
				$this->addHeaderAction();
				require_once './Services/Search/classes/class.ilRepositorySearchGUI.php';
				$rep_search =& new ilRepositorySearchGUI();
				$rep_search->setCallback($this,
					'addParticipantsObject',
					array()
				);

				// Set tabs
				$this->ctrl->setReturn($this, 'participants');
				$ret =& $this->ctrl->forwardCommand($rep_search);
				$this->tabs_gui->setTabActive('participants');
				break;

			case 'ilpageeditorgui':
			case 'iltestexpresspageobjectgui':
				$qid = $_REQUEST['q_id'];

				// :FIXME: does not work
				// $this->ctrl->saveParameterByClass(array('iltestexpresspageobjectgui', 'assorderingquestiongui', 'ilpageeditorgui', 'ilpcquestion', 'ilpcquestiongui'), 'test_express_mode');

				if(!$qid || $qid == 'Array')
				{
					$questions = $this->object->getQuestionTitlesAndIndexes();
					if(!is_array($questions))
						$questions = array();

					$keys = array_keys($questions);
					$qid  = $keys[0];

					$_REQUEST['q_id'] = $qid;
					$_GET['q_id']     = $qid;
					$_POST['q_id']    = $qid;
				}

				$this->prepareOutput();
				if(!in_array($cmd, array('addQuestion', 'browseForQuestions')))
				{
					$this->buildPageViewToolbar($qid);
				}

				if(!$qid || in_array($cmd, array('insertQuestions', 'browseForQuestions')))
				{
					require_once "./Modules/Test/classes/class.ilTestExpressPageObjectGUI.php";
					$pageObject              = new ilTestExpressPageObjectGUI (0);
					$pageObject->test_object = $this->object;
					$ret                     =& $this->ctrl->forwardCommand($pageObject);
					break;
				}
				require_once "./Services/Style/classes/class.ilObjStyleSheet.php";
				$this->tpl->setCurrentBlock("ContentStyle");
				$this->tpl->setVariable("LOCATION_CONTENT_STYLESHEET",
					ilObjStyleSheet::getContentStylePath(0));
				$this->tpl->parseCurrentBlock();

				// syntax style
				$this->tpl->setCurrentBlock("SyntaxStyle");
				$this->tpl->setVariable("LOCATION_SYNTAX_STYLESHEET",
					ilObjStyleSheet::getSyntaxStylePath());
				$this->tpl->parseCurrentBlock();
				require_once "./Modules/TestQuestionPool/classes/class.assQuestionGUI.php";

				$q_gui =& assQuestionGUI::_getQuestionGUI("", $qid);

				$q_gui->outAdditionalOutput();
				$q_gui->object->setObjId($this->object->getId());
				$question =& $q_gui->object;
				$this->ctrl->saveParameter($this, "q_id");

				#$this->lng->loadLanguageModule("content");
				$this->ctrl->setReturnByClass("ilTestExpressPageObjectGUI", "view");
				$this->ctrl->setReturn($this, "questions");

				require_once "./Modules/TestQuestionPool/classes/class.ilAssQuestionPage.php";
				require_once "./Modules/Test/classes/class.ilTestExpressPageObjectGUI.php";

				$page_gui = new ilTestExpressPageObjectGUI($qid);
				$page_gui->test_object = $this->object;
				$page_gui->setEditPreview(true);
				$page_gui->setEnabledTabs(false);
				if(strlen($this->ctrl->getCmd()) == 0)
				{
					$this->ctrl->setCmdClass(get_class($page_gui));
					$this->ctrl->setCmd("preview");
				}

				$page_gui->setQuestionHTML(array($q_gui->object->getId() => $q_gui->getPreview(TRUE)));
				$page_gui->setTemplateTargetVar("ADM_CONTENT");

				$page_gui->setOutputMode($this->object->evalTotalPersons() == 0 ? "edit" : 'preview');

				$page_gui->setHeader($question->getTitle());
				$page_gui->setFileDownloadLink($this->ctrl->getLinkTarget($this, "downloadFile"));
				$page_gui->setFullscreenLink($this->ctrl->getLinkTarget($this, "fullscreen"));
				$page_gui->setSourcecodeDownloadScript($this->ctrl->getLinkTarget($this));
				$page_gui->setPresentationTitle($question->getTitle() . ' ['. $this->lng->txt('question_id_short') . ': ' . $question->getId()  . ']');
				$ret =& $this->ctrl->forwardCommand($page_gui);

				global $ilTabs;
				$ilTabs->activateTab('assQuestions');

				$this->tpl->setContent($ret);
				break;

			case 'ilassquestionpagegui':
				require_once "./Modules/TestQuestionPool/classes/class.ilAssQuestionPageGUI.php";
				//echo $_REQUEST['prev_qid'];
				if($_REQUEST['prev_qid'])
				{
					$this->ctrl->setParameter($this, 'prev_qid', $_REQUEST['prev_qid']);
				}

				$this->prepareOutput();
				//global $___test_express_mode;
				//$___test_express_mode = true;
				$_GET['calling_test'] = $this->object->getRefId();
				include_once("./Services/Style/classes/class.ilObjStyleSheet.php");
				$this->tpl->setCurrentBlock("ContentStyle");
				$this->tpl->setVariable("LOCATION_CONTENT_STYLESHEET",
					ilObjStyleSheet::getContentStylePath(0));
				$this->tpl->parseCurrentBlock();

				// syntax style
				$this->tpl->setCurrentBlock("SyntaxStyle");
				$this->tpl->setVariable("LOCATION_SYNTAX_STYLESHEET",
					ilObjStyleSheet::getSyntaxStylePath());
				$this->tpl->parseCurrentBlock();
				require_once "./Modules/TestQuestionPool/classes/class.assQuestionGUI.php";
				$q_gui = assQuestionGUI::_getQuestionGUI("", $_GET["q_id"]);
				$q_gui->setQuestionTabs();
				$q_gui->outAdditionalOutput();
				$q_gui->object->setObjId($this->object->getId());
				$question =& $q_gui->object;
				$this->ctrl->saveParameter($this, "q_id");
				$this->lng->loadLanguageModule("content");
				$this->ctrl->setReturnByClass("ilAssQuestionPageGUI", "view");
				$this->ctrl->setReturn($this, "questions");
				$page_gui = new ilAssQuestionPageGUI($_GET["q_id"]);
				$page_gui->setEditPreview(true);
				if(strlen($this->ctrl->getCmd()) == 0)
				{
					$this->ctrl->setCmdClass(get_class($page_gui));
					$this->ctrl->setCmd("preview");
				}
				$page_gui->setQuestionHTML(array($q_gui->object->getId() => $q_gui->getPreview(TRUE)));
				$page_gui->setTemplateTargetVar("ADM_CONTENT");
				$page_gui->setOutputMode($this->object->evalTotalPersons() == 0 ? "edit" : 'preview');
				$page_gui->setHeader($question->getTitle());
				$page_gui->setPresentationTitle($question->getTitle() . ' ['. $this->lng->txt('question_id_short') . ': ' . $question->getId()  . ']');
				$ret =& $this->ctrl->forwardCommand($page_gui);
				$this->tpl->setContent($ret);
				break;
				
			case 'ilassspecfeedbackpagegui':
				require_once "./Modules/TestQuestionPool/classes/feedback/class.ilAssSpecFeedbackPageGUI.php";
				$pg_gui = new ilAssSpecFeedbackPageGUI((int) $_GET["feedback_id"]);
				$this->ctrl->forwardCommand($pg_gui);
				break;
				
			case 'ilassgenfeedbackpagegui':
				require_once "./Modules/TestQuestionPool/classes/feedback/class.ilAssGenFeedbackPageGUI.php";
				$pg_gui = new ilAssGenFeedbackPageGUI((int) $_GET["feedback_id"]);
				$this->ctrl->forwardCommand($pg_gui);
				break;

			case "ilcommonactiondispatchergui":
				require_once "Services/Object/classes/class.ilCommonActionDispatcherGUI.php";
				$gui = ilCommonActionDispatcherGUI::getInstanceFromAjaxCall();
				$this->ctrl->forwardCommand($gui);
				break;

			case 'ilassquestionhintsgui':

				$this->prepareSubGuiOutput();

				// set return target
				$this->ctrl->setReturn($this, "questions");

				// set context tabs
				require_once 'Modules/TestQuestionPool/classes/class.assQuestionGUI.php';
				$questionGUI =& assQuestionGUI::_getQuestionGUI($q_type, $_GET['q_id']);
				$questionGUI->object->setObjId($this->object->getId());
				$questionGUI->setQuestionTabs();

				// forward to ilAssQuestionHintsGUI
				require_once 'Modules/TestQuestionPool/classes/class.ilAssQuestionHintsGUI.php';
				$gui = new ilAssQuestionHintsGUI($questionGUI);
				$ilCtrl->forwardCommand($gui);

				break;

			case 'ilassquestionfeedbackeditinggui':

				$this->prepareSubGuiOutput();

				// set return target
				$this->ctrl->setReturn($this, "questions");

				// set context tabs
				require_once 'Modules/TestQuestionPool/classes/class.assQuestionGUI.php';
				$questionGUI = assQuestionGUI::_getQuestionGUI($q_type, $_GET['q_id']);
				$questionGUI->object->setObjId($this->object->getId());
				$questionGUI->setQuestionTabs();

				// forward to ilAssQuestionFeedbackGUI
				require_once 'Modules/TestQuestionPool/classes/class.ilAssQuestionFeedbackEditingGUI.php';
				$gui = new ilAssQuestionFeedbackEditingGUI($questionGUI, $ilCtrl, $ilAccess, $tpl, $ilTabs, $lng);
				$ilCtrl->forwardCommand($gui);

				break;

			case 'iltesttoplistgui':
				$this->prepareOutput();
				require_once './Modules/Test/classes/class.ilTestToplistGUI.php';
				$gui = new ilTestToplistGUI($this);
				$this->ctrl->forwardCommand($gui);
				break;

			case 'ilscoringadjustmentgui':
				$this->prepareOutput();
				require_once './Modules/Test/classes/class.ilScoringAdjustmentGUI.php';
				$gui = new ilScoringAdjustmentGUI($this->object);
				$this->ctrl->forwardCommand($gui);
				break;
			
			case '':
			case 'ilobjtestgui':
				$this->prepareOutput();
				$this->addHeaderAction();
				if(preg_match("/deleteqpl_\d+/", $cmd))
				{
					$cmd = "randomQuestions";
				}
				if((strcmp($cmd, "properties") == 0) && ($_GET["browse"]))
				{
					$this->questionBrowser();
					return;
				}
				if((strcmp($cmd, "properties") == 0) && ($_GET["up"] || $_GET["down"]))
				{
					$this->questionsObject();
					return;
				}
				$cmd .= "Object";
				$ret =& $this->$cmd();
				break;
			default:
				// elba hack for storing question id for inserting new question after
				if($_REQUEST['prev_qid'])
				{
					global $___prev_question_id;
					$___prev_question_id = $_REQUEST['prev_qid'];
					$this->ctrl->setParameterByClass('ilassquestionpagegui', 'prev_qid', $_REQUEST['prev_qid']);
					$this->ctrl->setParameterByClass($_GET['sel_question_types'] . 'gui', 'prev_qid', $_REQUEST['prev_qid']);
				}
				$this->create_question_mode = true;
				$this->prepareOutput();

				$this->ctrl->setReturn($this, "questions");
				require_once "./Modules/TestQuestionPool/classes/class.assQuestionGUI.php";
				$q_gui =& assQuestionGUI::_getQuestionGUI($_GET['sel_question_types'], $_GET["q_id"]);
				$q_gui->object->setObjId($this->object->getId());
				if(!$_GET['sel_question_types'])
					$qType = assQuestion::getQuestionTypeFromDb($_GET['q_id']);
				else
				{
					$qType = $_GET['sel_question_types'];
				}
				$this->ctrl->setParameterByClass($qType . "GUI", 'prev_qid', $_REQUEST['prev_qid']);
				$this->ctrl->setParameterByClass($qType . "GUI", 'test_ref_id', $_REQUEST['ref_id']);
				$this->ctrl->setParameterByClass($qType . "GUI", 'q_id', $_REQUEST['q_id']);
				if($_REQUEST['test_express_mode'])
					$this->ctrl->setParameterByClass($qType . "GUI", 'test_express_mode', 1);

				#global $___test_express_mode;
				#$___test_express_mode = true;
				if(!$q_gui->isSaveCommand())
					$_GET['calling_test'] = $this->object->getRefId();

				$q_gui->setQuestionTabs();
				#unset($___test_express_mode);
				$ret =& $this->ctrl->forwardCommand($q_gui);
				break;
		}
		if ( !in_array(strtolower($_GET["baseClass"]), array('iladministrationgui', 'ilrepositorygui')) &&
			$this->getCreationMode() != true)
		{
			$this->tpl->show();
		}
	}

	
	public function isValidRequestOnBrokenQuestionSetDepencies($nextClass, $cmd)
	{
		//vd($nextClass, $cmd);
		
		if( !$this->object->participantDataExist() )
		{
			return true;
		}
		
		switch( $nextClass )
		{
			case 'ilobjtestdynamicquestionsetconfiggui':
				
			case 'ilmdeditorgui':
			case 'ilpermissiongui':
				
				return true;
				
			case 'ilobjtestgui':
			case '':
				
				$cmds = array(
					'infoScreen', 'participants', 'npSetFilter', 'npResetFilter',
					'deleteAllUserResults', 'confirmDeleteAllUserResults',
					'deleteSingleUserResults', 'confirmDeleteSelectedUserData', 'cancelDeleteSelectedUserData'
				);
				
				if( in_array($cmd, $cmds) )
				{
					return true;
				}
				
				break;
		}
		
		return false;
	}
	
	/**
	 * @param $show_pass_details
	 * @param $show_answers
	 * @param $show_reached_points
	 * @param $show_user_results
	 *
	 * @return ilTemplate
	 */
	public function createUserResults($show_pass_details, $show_answers, $show_reached_points, $show_user_results)
	{
		$template = new ilTemplate("tpl.il_as_tst_participants_result_output.html", TRUE, TRUE, "Modules/Test");
		include_once "./Modules/Test/classes/class.ilTestServiceGUI.php";
		$serviceGUI = new ilTestServiceGUI($this->object);
		$count      = 0;
		foreach ($show_user_results as $key => $active_id)
		{
			$count++;
			$results = "";
			if ($this->object->getFixedParticipants())
			{
				$active_id = $this->object->getActiveIdOfUser( $active_id );
			}
			if ($active_id > 0)
			{
				$results = $serviceGUI->getResultsOfUserOutput(
					$this->testSessionFactory->getSession( $active_id ),
					$active_id,
					$this->object->_getResultPass( $active_id ),
					$show_pass_details,
					$show_answers,
					FALSE,
					$show_reached_points
				);
			}
			if ($count < count( $show_user_results ))
			{
				$template->touchBlock( "break" );
			}
			$template->setCurrentBlock( "user_result" );
			$template->setVariable( "USER_RESULT", $results );
			$template->parseCurrentBlock();
		}
		$template->setVariable( "BACK_TEXT", $this->lng->txt( "back" ) );
		$template->setVariable( "BACK_URL", $this->ctrl->getLinkTargetByClass( "ilobjtestgui", "participants" ) );
		$template->setVariable( "PRINT_TEXT", $this->lng->txt( "print" ) );
		$template->setVariable( "PRINT_URL", "javascript:window.print();" );
		return $template;
	}

	private function redirectTo_ilObjTestSettingsGeneralGUI_showForm_Object()
	{
		require_once 'Modules/Test/classes/class.ilObjTestSettingsGeneralGUI.php';
		$this->ctrl->redirectByClass('ilObjTestSettingsGeneralGUI', ilObjTestSettingsGeneralGUI::CMD_SHOW_FORM);
	}
	
	/**
	 * prepares ilias to get output rendered by sub gui class
	 * 
	 * @global ilLocator $ilLocator
	 * @global ilTemplate $tpl
	 * @global ilObjUser $ilUser
	 * @return boolean
	 */
	private function prepareSubGuiOutput()
	{
		global $ilUser;

		$this->tpl->getStandardTemplate();

		// set locator
		$this->setLocator();
		
		// catch feedback message
		ilUtil::infoPanel();

		// set title and description and title icon
		$this->setTitleAndDescription();

		// BEGIN WebDAV: Display Mount Webfolder icon.
		require_once 'Services/WebDAV/classes/class.ilDAVServer.php';
		if (ilDAVServer::_isActive() && $ilUser->getId() != ANONYMOUS_USER_ID)
		{
			$this->showMountWebfolderIcon();
		}
		// END WebDAV: Display Mount Webfolder icon.
	}

	function runObject()
	{
		$this->ctrl->redirect($this, "infoScreen");
	}
	
	function outEvaluationObject()
	{
		$this->ctrl->redirectByClass("iltestevaluationgui", "outEvaluation");
	}

	/**
	* form for new test object import
	*/
	function importFileObject()
	{
		$form = $this->initImportForm($_REQUEST["new_type"]);
		if($form->checkInput())
		{
			$this->ctrl->setParameter($this, "new_type", $this->type);
			$this->uploadTstObject();
		}

		// display form to correct errors
		$form->setValuesByPost();
		$this->tpl->setContent($form->getHTML());
	}
	
	function addDidacticTemplateOptions(array &$a_options) 
	{
		include_once("./Modules/Test/classes/class.ilObjTest.php");
		$tst = new ilObjTest();
		$defaults = $tst->getAvailableDefaults();
		if (count($defaults))
		{
			foreach ($defaults as $row)
			{
				$a_options["tstdef_".$row["test_defaults_id"]] = array($row["name"],
					$this->lng->txt("tst_default_settings"));
			}
		}

		// using template?
		include_once "Services/Administration/classes/class.ilSettingsTemplate.php";
		$templates = ilSettingsTemplate::getAllSettingsTemplates("tst");
		if($templates)
		{
			foreach($templates as $item)
			{
				$a_options["tsttpl_".$item["id"]] = array($item["title"],
					nl2br(trim($item["description"])));
			}
		}		
	}
	
	/**
	* save object
	* @access	public
	*/
	function afterSave(ilObject $a_new_object)
	{
		// added by JP and moved here by BH
		// BH: why is the property not initialised with true within the object class per default .. ?
		$a_new_object->setPoolUsage(true);
		
		$tstdef = $this->getDidacticTemplateVar("tstdef");
		if ($tstdef) 
		{
			$testDefaultsId = $tstdef;
			$testDefaults = ilObjTest::_getTestDefaults($testDefaultsId);
			$a_new_object->applyDefaults($testDefaults);
		}

		$template_id = $this->getDidacticTemplateVar("tsttpl");
		if($template_id)
		{			
			include_once "Services/Administration/classes/class.ilSettingsTemplate.php";
			$template = new ilSettingsTemplate($template_id, ilObjAssessmentFolderGUI::getSettingsTemplateConfig());

			$template_settings = $template->getSettings();
			if($template_settings)
			{
				$this->applyTemplate($template_settings, $a_new_object);
			}

			$a_new_object->setTemplate($template_id);
		}
		
		$a_new_object->saveToDb();

		// always send a message
		ilUtil::sendSuccess($this->lng->txt("object_added"),true);
		$this->ctrl->setParameter($this, 'ref_id', $a_new_object->getRefId());
		$this->ctrl->redirectByClass('ilObjTestSettingsGeneralGUI');
	}

	function backToRepositoryObject()
	{
		include_once "./Services/Utilities/classes/class.ilUtil.php";
		$path = $this->tree->getPathFull($this->object->getRefID());
		ilUtil::redirect($this->getReturnLocation("cancel","./ilias.php?baseClass=ilRepositoryGUI&cmd=frameset&ref_id=" . $path[count($path) - 2]["child"]));
	}
	
	function backToCourseObject()
	{
		include_once "./Services/Utilities/classes/class.ilUtil.php";
		ilUtil::redirect($this->getReturnLocation("cancel","./ilias.php?baseClass=ilRepositoryGUI&ref_id=".(int) $_GET['crs_show_result']));
	}

	/**
	* imports test and question(s)
	*/
	function uploadTstObject()
	{
		if ($_FILES["xmldoc"]["error"] > UPLOAD_ERR_OK)
		{
			ilUtil::sendFailure($this->lng->txt("error_upload"));
			$this->createObject();
			return;
		}
		include_once("./Modules/Test/classes/class.ilObjTest.php");
		// create import directory
		$basedir = ilObjTest::_createImportDirectory();

		// copy uploaded file to import directory
		$file = pathinfo($_FILES["xmldoc"]["name"]);
		$full_path = $basedir."/".$_FILES["xmldoc"]["name"];
		ilUtil::moveUploadedFile($_FILES["xmldoc"]["tmp_name"], $_FILES["xmldoc"]["name"], $full_path);

		// unzip file
		ilUtil::unzip($full_path);

		// determine filenames of xml files
		$subdir = basename($file["basename"],".".$file["extension"]);
		ilObjTest::_setImportDirectory($basedir);
		$xml_file = ilObjTest::_getImportDirectory().'/'.$subdir.'/'.$subdir.".xml";
		$qti_file = ilObjTest::_getImportDirectory().'/'.$subdir.'/'. preg_replace("/test|tst/", "qti", $subdir).".xml";
		$results_file = ilObjTest::_getImportDirectory().'/'.$subdir.'/'. preg_replace("/test|tst/", "results", $subdir).".xml";

		// start verification of QTI files
		include_once "./Services/QTI/classes/class.ilQTIParser.php";
		$qtiParser = new ilQTIParser($qti_file, IL_MO_VERIFY_QTI, 0, "");
		$result = $qtiParser->startParsing();
		$founditems =& $qtiParser->getFoundItems();
		
		if (count($founditems) == 0)
		{
			// nothing found

			// delete import directory
			ilUtil::delDir($basedir);

			ilUtil::sendInfo($this->lng->txt("tst_import_no_items"));
			$this->createObject();
			return;
		}
		
		$complete = 0;
		$incomplete = 0;
		foreach ($founditems as $item)
		{
			if (strlen($item["type"]))
			{
				$complete++;
			}
			else
			{
				$incomplete++;
			}
		}
		
		if ($complete == 0)
		{
			// delete import directory
			ilUtil::delDir($basedir);

			ilUtil::sendInfo($this->lng->txt("qpl_import_non_ilias_files"));
			$this->createObject();
			return;
		}
		
		$_SESSION["tst_import_results_file"] = $results_file;
		$_SESSION["tst_import_xml_file"] = $xml_file;
		$_SESSION["tst_import_qti_file"] = $qti_file;
		$_SESSION["tst_import_subdir"] = $subdir;
		// display of found questions
		$this->tpl->addBlockFile("ADM_CONTENT", "adm_content", "tpl.tst_import_verification.html", "Modules/Test");
		$row_class = array("tblrow1", "tblrow2");
		$counter = 0;
		foreach ($founditems as $item)
		{
			$this->tpl->setCurrentBlock("verification_row");
			$this->tpl->setVariable("ROW_CLASS", $row_class[$counter++ % 2]);
			$this->tpl->setVariable("QUESTION_TITLE", $item["title"]);
			$this->tpl->setVariable("QUESTION_IDENT", $item["ident"]);
			include_once "./Services/QTI/classes/class.ilQTIItem.php";
			switch ($item["type"])
			{
				case "MULTIPLE CHOICE QUESTION":
				case QT_MULTIPLE_CHOICE_MR:
					$this->tpl->setVariable("QUESTION_TYPE", $this->lng->txt("qt_multiple_choice"));
					break;
				case "SINGLE CHOICE QUESTION":
				case QT_MULTIPLE_CHOICE_SR:
					$this->tpl->setVariable("QUESTION_TYPE", $this->lng->txt("assSingleChoice"));
					break;
				case "NUMERIC QUESTION":
				case QT_NUMERIC:
					$this->tpl->setVariable("QUESTION_TYPE", $this->lng->txt("assNumeric"));
					break;
				case "TEXTSUBSET QUESTION":
				case QT_TEXTSUBSET:
					$this->tpl->setVariable("QUESTION_TYPE", $this->lng->txt("assTextSubset"));
					break;
				case "CLOZE QUESTION":
				case QT_CLOZE:
					$this->tpl->setVariable("QUESTION_TYPE", $this->lng->txt("assClozeTest"));
					break;
				case "IMAGE MAP QUESTION":
				case QT_IMAGEMAP:
					$this->tpl->setVariable("QUESTION_TYPE", $this->lng->txt("assImagemapQuestion"));
					break;
				case "JAVA APPLET QUESTION":
				case QT_JAVAAPPLET:
					$this->tpl->setVariable("QUESTION_TYPE", $this->lng->txt("assJavaApplet"));
					break;
				case "MATCHING QUESTION":
				case QT_MATCHING:
					$this->tpl->setVariable("QUESTION_TYPE", $this->lng->txt("assMatchingQuestion"));
					break;
				case "ORDERING QUESTION":
				case QT_ORDERING:
					$this->tpl->setVariable("QUESTION_TYPE", $this->lng->txt("assOrderingQuestion"));
					break;
				case "TEXT QUESTION":
				case QT_TEXT:
					$this->tpl->setVariable("QUESTION_TYPE", $this->lng->txt("assTextQuestion"));
					break;
			}
			$this->tpl->parseCurrentBlock();
		}

		// on import creation screen the pool was chosen (-1 for no pool)
		// BUT when no pool is available the input on creation screen is missing, so the field value -1 for no pool is not submitted.
		$QplOrTstID = isset($_POST["qpl"]) && (int)$_POST["qpl"] != 0 ? $_POST["qpl"] : -1;
		
		$this->tpl->setCurrentBlock("adm_content");
		$this->tpl->setVariable("TEXT_TYPE", $this->lng->txt("question_type"));
		$this->tpl->setVariable("TEXT_TITLE", $this->lng->txt("question_title"));
		$this->tpl->setVariable("FOUND_QUESTIONS_INTRODUCTION", $this->lng->txt("tst_import_verify_found_questions"));
		$this->tpl->setVariable("VERIFICATION_HEADING", $this->lng->txt("import_tst"));
		$this->tpl->setVariable("FORMACTION", $this->ctrl->getFormAction($this));
		$this->tpl->setVariable("ARROW", ilUtil::getImagePath("arrow_downright.png"));
		$this->tpl->setVariable("QUESTIONPOOL_ID", $QplOrTstID);
		$this->tpl->setVariable("VALUE_IMPORT", $this->lng->txt("import"));
		$this->tpl->setVariable("VALUE_CANCEL", $this->lng->txt("cancel"));
		$this->tpl->parseCurrentBlock();
	}
	
	/**
	* imports question(s) into the questionpool (after verification)
	*/
	function importVerifiedFileObject()
	{
		include_once "./Modules/Test/classes/class.ilObjTest.php";
		// create new questionpool object
		$newObj = new ilObjTest(0, true);
		// set type of questionpool object
		$newObj->setType($_GET["new_type"]);
		// set title of questionpool object to "dummy"
		$newObj->setTitle("dummy");
		// set description of questionpool object
		$newObj->setDescription("test import");
		// create the questionpool class in the ILIAS database (object_data table)
		$newObj->create(true);
		// create a reference for the questionpool object in the ILIAS database (object_reference table)
		$newObj->createReference();
		// put the questionpool object in the administration tree
		$newObj->putInTree($_GET["ref_id"]);
		// get default permissions and set the permissions for the questionpool object
		$newObj->setPermissions($_GET["ref_id"]);
		// notify the questionpool object and all its parent objects that a "new" object was created
		$newObj->notify("new",$_GET["ref_id"],$_GET["parent_non_rbac_id"],$_GET["ref_id"],$newObj->getRefId());
		// empty mark schema
		$newObj->mark_schema->flush();

		// start parsing of QTI files
		include_once "./Services/QTI/classes/class.ilQTIParser.php";

		// Handle selection of "no questionpool" as qpl_id = -1 -> use test object id instead.
		// TODO: chek if empty strings in $_POST["qpl_id"] relates to a bug or not
		if ($_POST["qpl_id"] == "-1")
		{
			$qpl_id = $newObj->id;
		} 
		else 
		{
			$qpl_id = $_POST["qpl_id"];
		}

		$qtiParser = new ilQTIParser($_SESSION["tst_import_qti_file"], IL_MO_PARSE_QTI, $qpl_id, $_POST["ident"]);
		$qtiParser->setTestObject($newObj);
		$result = $qtiParser->startParsing();
		$newObj->saveToDb();

		// import page data
		include_once ("./Modules/LearningModule/classes/class.ilContObjParser.php");
		$contParser = new ilContObjParser($newObj, $_SESSION["tst_import_xml_file"], $_SESSION["tst_import_subdir"]);
		$contParser->setQuestionMapping($qtiParser->getImportMapping());
		$contParser->startParsing();

		// import test results
		if (@file_exists($_SESSION["tst_import_results_file"]))
		{
			include_once ("./Modules/Test/classes/class.ilTestResultsImportParser.php");
			$results = new ilTestResultsImportParser($_SESSION["tst_import_results_file"], $newObj);
			$results->startParsing();
		}

		// delete import directory
		ilUtil::delDir(dirname(ilObjTest::_getImportDirectory()));
		ilUtil::sendSuccess($this->lng->txt("object_imported"),true);

		$newObj->updateMetaData();
		
		ilUtil::redirect("ilias.php?ref_id=".$newObj->getRefId().
				"&baseClass=ilObjTestGUI");
	}
	
	/**
	* display status information or report errors messages
	* in case of error
	*
	* @access	public
	*/
	function uploadObject($redirect = true)
	{
		$this->uploadTstObject();
	}

	function confirmScoringObject($confirmCmd = 'saveScoring', $cancelCmd = 'scoring')
	{
		$this->tpl->addBlockFile("ADM_CONTENT", "adm_content", "tpl.il_as_tst_properties_save_confirmation.html", "Modules/Test");
		$information = $this->lng->txt('tst_trigger_result_refreshing');

		foreach ($_POST as $key => $value)
		{
			if (strcmp($key, "cmd") == 0)
			{
				continue;
			}

			$this->populateHiddenField( $value, $key );
		}
		$this->tpl->setCurrentBlock("hidden_variable");
		$this->tpl->setVariable("HIDDEN_KEY", "tst_properties_confirmation");
		$this->tpl->setVariable("HIDDEN_VALUE", "1");
		$this->tpl->parseCurrentBlock();
		$this->tpl->setCurrentBlock("adm_content");
		$this->tpl->setVariable("TXT_CONFIRMATION", $this->lng->txt("confirmation"));
		$this->tpl->setVariable("TXT_INFORMATION", $information);
		$this->tpl->setVariable("FORM_ACTION", $this->ctrl->getFormAction($this));
		$this->tpl->setVariable("CMD_CONFIRM", $confirmCmd);
		$this->tpl->setVariable("BTN_CONFIRM", $this->lng->txt("confirm"));
		$this->tpl->setVariable("CMD_CANCEL", $cancelCmd);
		$this->tpl->setVariable("BTN_CANCEL", $this->lng->txt("cancel"));
		$this->tpl->parseCurrentBlock();
	}

	/**
	 * @param        $value
	 * @param        $key
	 * @param string $path
	 */
	public function populateHiddenField($value, $key, $path = '')
	{
		if (is_array( $value ))
		{
			foreach ($value as $k => $v)
			{
				if (is_array($v))
				{
					$this->populateHiddenField($v, $key, $path .'['.$k.']');
				}
				else
				{
					$this->tpl->setCurrentBlock( "hidden_variable" );
					$this->tpl->setVariable( "HIDDEN_KEY", $key . $path . "[" . $k . "]" );
					$this->tpl->setVariable( "HIDDEN_VALUE", $v );
					$this->tpl->parseCurrentBlock();
				}
			}
		}
		else
		{
			$this->tpl->setCurrentBlock( "hidden_variable" );
			$this->tpl->setVariable( "HIDDEN_KEY", $key );
			$this->tpl->setVariable( "HIDDEN_VALUE", $value );
			$this->tpl->parseCurrentBlock();
		}
	}

	/**
	* Displays a save confirmation dialog for test properties
	*
	* Displays a save confirmation dialog for test properties when
	* already defined questions or question pools get lost after saving
	*
	* @param int $direction Direction of the change (0 = from random test to standard, anything else = from standard to random test)
	* @param string $confirmCmd
	* @param string $cancelCmd
	* @access	private
	*/
	function confirmChangeProperties($direction = self::SWITCH_RANDOM_TEST_SETTING_TO_DISABLED, $confirmCmd = 'saveProperties', $cancelCmd = 'properties')
	{
		$this->tpl->addBlockFile("ADM_CONTENT", "adm_content", "tpl.il_as_tst_properties_save_confirmation.html", "Modules/Test");
		$information = "";
		switch ($direction)
		{
			case self::SWITCH_RANDOM_TEST_SETTING_TO_DISABLED:
				$information = $this->lng->txt("change_properties_from_random_to_standard");
				break;
			case self::SWITCH_RANDOM_TEST_SETTING_TO_ENABLED:
			default:
				$information = $this->lng->txt("change_properties_from_standard_to_random");
				break;
		}
		foreach ($_POST as $key => $value)
		{
			if (strcmp($key, "cmd") != 0)
			{
				if (is_array($value))
				{
					foreach ($value as $k => $v)
					{
						$this->tpl->setCurrentBlock("hidden_variable");
						$this->tpl->setVariable("HIDDEN_KEY", $key . "[" . $k . "]");
						$this->tpl->setVariable("HIDDEN_VALUE", $v);
						$this->tpl->parseCurrentBlock();
					}
				}
				else
				{
					$this->tpl->setCurrentBlock("hidden_variable");
					$this->tpl->setVariable("HIDDEN_KEY", $key);
					$this->tpl->setVariable("HIDDEN_VALUE", $value);
					$this->tpl->parseCurrentBlock();
				}
			}
		}
		$this->tpl->setCurrentBlock("hidden_variable");
		$this->tpl->setVariable("HIDDEN_KEY", "tst_properties_confirmation");
		$this->tpl->setVariable("HIDDEN_VALUE", "1");
		$this->tpl->parseCurrentBlock();
		$this->tpl->setCurrentBlock("adm_content");
		$this->tpl->setVariable("TXT_CONFIRMATION", $this->lng->txt("confirmation"));
		$this->tpl->setVariable("TXT_INFORMATION", $information);
		$this->tpl->setVariable("FORM_ACTION", $this->ctrl->getFormAction($this));
		$this->tpl->setVariable("CMD_CONFIRM", $confirmCmd);
		$this->tpl->setVariable("BTN_CONFIRM", $this->lng->txt("confirm"));
		$this->tpl->setVariable("CMD_CANCEL", $cancelCmd);
		$this->tpl->setVariable("BTN_CANCEL", $this->lng->txt("cancel"));
		$this->tpl->parseCurrentBlock();
	}
	
	/**
	* Save the form input of the scoring form
	*
	* @access	public
	*/
	function saveScoringObject()
	{
		$hasErrors = $this->scoringObject(true);
		if (!$hasErrors)
		{
			$total = $this->object->evalTotalPersons();

			$this->object->setCountSystem($_POST["count_system"]);
			$this->object->setMCScoring($_POST["mc_scoring"]);
			$this->object->setScoreCutting($_POST["score_cutting"]);
			$this->object->setPassScoring($_POST["pass_scoring"]);
			
			if( isset($_POST['obligations_enabled']) && $_POST['obligations_enabled'] )
			{
				$this->object->setObligationsEnabled(true);
			}
			else
			{
				$this->object->setObligationsEnabled(false);
			}
			
			if( isset($_POST['offer_hints']) && $_POST['offer_hints'] )
			{
				$this->object->setOfferingQuestionHintsEnabled(true);
			}
			else
			{
				$this->object->setOfferingQuestionHintsEnabled(false);
			}


                        /*
			$this->object->setAnswerFeedback((is_array($_POST['instant_feedback']) && in_array('instant_feedback_answer', $_POST['instant_feedback'])) ? 1 : 0);
			$this->object->setAnswerFeedbackPoints((is_array($_POST['instant_feedback']) && in_array('instant_feedback_points', $_POST['instant_feedback'])) ? 1 : 0);
			$this->object->setInstantFeedbackSolution((is_array($_POST['instant_feedback']) && in_array('instant_feedback_solution', $_POST['instant_feedback'])) ? 1 : 0);
                        */

            /**
                         * I introduced a single setter for instant_feedback options
                         * @author jposselt at databay . de
                         */
            $this->object->setScoringFeedbackOptionsByArray($_POST['instant_feedback']);


			$this->object->setScoreReporting($_POST["results_access"]);
			if ($this->object->getScoreReporting() == REPORT_AFTER_DATE)
			{
				$this->object->setReportingDate(str_replace(array(':', '-'), '', $_POST["reporting_date"]['date'].$_POST["reporting_date"]['time']));
			}
			else
			{
				$this->object->setReportingDate('');
			}

			$this->object->setShowPassDetails((is_array($_POST['results_presentation']) && in_array('pass_details', $_POST['results_presentation'])) ? 1 : 0);
			$this->object->setShowSolutionDetails((is_array($_POST['results_presentation']) && in_array('solution_details', $_POST['results_presentation'])) ? 1 : 0);
			$this->object->setShowSolutionPrintview((is_array($_POST['results_presentation']) && in_array('solution_printview', $_POST['results_presentation'])) ? 1 : 0);
			$this->object->setShowSolutionFeedback((is_array($_POST['results_presentation']) && in_array('solution_feedback', $_POST['results_presentation'])) ? 1 : 0);
			$this->object->setShowSolutionAnswersOnly((is_array($_POST['results_presentation']) && in_array('solution_answers_only', $_POST['results_presentation'])) ? 1 : 0);
			$this->object->setShowSolutionSignature((is_array($_POST['results_presentation']) && in_array('solution_signature', $_POST['results_presentation'])) ? 1 : 0);
			$this->object->setShowSolutionSuggested((is_array($_POST['results_presentation']) && in_array('solution_suggested', $_POST['results_presentation'])) ? 1 : 0);
			$this->object->setShowSolutionListComparison((is_array($_POST['results_presentation']) && in_array('solution_compare', $_POST['results_presentation'])) ? 1 : 0);
			$this->object->setExportSettingsSingleChoiceShort((is_array($_POST['export_settings']) && in_array('exp_sc_short', $_POST['export_settings'])) ? 1 : 0);

			$this->object->setPrintBestSolutionWithResult((int) $_POST['print_bs_with_res'] ? true : false);
			
			$this->object->setPassDeletionAllowed((bool)$_POST['pass_deletion_allowed']);

			$this->object->saveToDb(true);
			ilUtil::sendSuccess($this->lng->txt("msg_obj_modified"), TRUE);

			if ($total != 0)
			{
				$this->object->recalculateScores();
			}
			
			$this->ctrl->redirect($this, "scoring");
		}
	}
	
	/**
	* Display and fill the scoring settings form of the test
	*
	* @access	public
	*/
	function scoringObject($checkonly = FALSE)
	{
		global $ilAccess;
		if (!$ilAccess->checkAccess("write", "", $this->ref_id)) 
		{
			// allow only write access
			ilUtil::sendInfo($this->lng->txt("cannot_edit_test"), true);
			$this->ctrl->redirect($this, "infoScreen");
		}


		// using template?
		$template_settings = null;
		$template = $this->object->getTemplate();

		if($template)
		{
			include_once "Services/Administration/classes/class.ilSettingsTemplate.php";
                        include_once "Modules/Test/classes/class.ilObjAssessmentFolderGUI.php";

			$template = new ilSettingsTemplate($template, ilObjAssessmentFolderGUI::getSettingsTemplateConfig());

			$template_settings = $template->getSettings();
		}

		$save = (strcmp($this->ctrl->getCmd(), "saveScoring") == 0) ? TRUE : FALSE;
		$total = $this->object->evalTotalPersons();
		$this->tpl->addJavascript("./Services/JavaScript/js/Basic.js");

		// Determine settings availability
		$setting_available = true; // In general, the scoring settings are available.

		if ($total != 0)
		{
			$setting_available = false; // Unless there are results from users.
		}
		if ($this->object->getScoreReporting() == 4)
		{
			$setting_available = true; // But if the results are not public since they will never be
		}
		if ($this->object->getScoreReporting() == 3 && $this->object->getReportingDate() > time())
		{
			$setting_available = true;// or the presentation date is not reached, then we can still edit them.
		}
		
		include_once("./Services/Form/classes/class.ilPropertyFormGUI.php");
		$form = new ilPropertyFormGUI();
		$form->setFormAction($this->ctrl->getFormAction($this));
		$form->setTableWidth("100%");
		$form->setId("test_properties_scoring");

		// scoring properties
		$header = new ilFormSectionHeaderGUI();
		$header->setTitle($this->lng->txt("test_scoring"));
		$form->addItem($header);
		
		// scoring system
		$count_system = new ilRadioGroupInputGUI($this->lng->txt("tst_text_count_system"), "count_system");
		$count_system->addOption(new ilRadioOption($this->lng->txt("tst_count_partial_solutions"), 0, ''));
		$count_system->addOption(new ilRadioOption($this->lng->txt("tst_count_correct_solutions"), 1, ''));
		$count_system->setValue($this->object->getCountSystem());
		$count_system->setInfo($this->lng->txt("tst_count_system_description"));
		$count_system->setDisabled(!$setting_available);
		$form->addItem($count_system);

		// mc questions
		$mc_scoring = new ilRadioGroupInputGUI($this->lng->txt("tst_score_mcmr_questions"), "mc_scoring");
		$mc_scoring->addOption(new ilRadioOption($this->lng->txt("tst_score_mcmr_zero_points_when_unanswered"), 0, ''));
		$mc_scoring->addOption(new ilRadioOption($this->lng->txt("tst_score_mcmr_use_scoring_system"), 1, ''));
		$mc_scoring->setValue($this->object->getMCScoring());
		$mc_scoring->setInfo($this->lng->txt("tst_score_mcmr_questions_description"));
		$mc_scoring->setDisabled(!$setting_available);
		$form->addItem($mc_scoring);
		
		// score cutting
		$score_cutting = new ilRadioGroupInputGUI($this->lng->txt("tst_score_cutting"), "score_cutting");
		$score_cutting->addOption(new ilRadioOption($this->lng->txt("tst_score_cut_question"), 0, ''));
		$score_cutting->addOption(new ilRadioOption($this->lng->txt("tst_score_cut_test"), 1, ''));
		$score_cutting->setValue($this->object->getScoreCutting());
		$score_cutting->setInfo($this->lng->txt("tst_score_cutting_description"));
		$score_cutting->setDisabled(!$setting_available);
		$form->addItem($score_cutting);
		
		// pass scoring
		$pass_scoring = new ilRadioGroupInputGUI($this->lng->txt("tst_pass_scoring"), "pass_scoring");
		$pass_scoring->addOption(new ilRadioOption($this->lng->txt("tst_pass_last_pass"), 0, ''));
		$pass_scoring->addOption(new ilRadioOption($this->lng->txt("tst_pass_best_pass"), 1, ''));
		$pass_scoring->setValue($this->object->getPassScoring());
		$pass_scoring->setInfo($this->lng->txt("tst_pass_scoring_description"));
		$pass_scoring->setDisabled(!$setting_available);
		$form->addItem($pass_scoring);

		// test presentation
		$header_tp = new ilFormSectionHeaderGUI();
		$header_tp->setTitle($this->lng->txt("test_presentation"));
		$form->addItem($header_tp);
		
		// enable obligations
		$checkBoxEnableObligations = new ilCheckboxInputGUI($this->lng->txt("tst_setting_enable_obligations_label"), "obligations_enabled");
		$checkBoxEnableObligations->setChecked($this->object->areObligationsEnabled());
		$checkBoxEnableObligations->setInfo($this->lng->txt("tst_setting_enable_obligations_info"));
		if( $total )
		{
			$checkBoxEnableObligations->setDisabled(true);
		}
		$form->addItem($checkBoxEnableObligations);
		
		
		// offer hints
		$checkBoxOfferHints = new ilCheckboxInputGUI($this->lng->txt("tst_setting_offer_hints_label"), "offer_hints");
		$checkBoxOfferHints->setChecked($this->object->isOfferingQuestionHintsEnabled());
		$checkBoxOfferHints->setInfo($this->lng->txt("tst_setting_offer_hints_info"));
		if( $total )
		{
			$checkBoxOfferHints->setDisabled(true);
		}
		$form->addItem($checkBoxOfferHints);

		// instant feedback
		$instant_feedback = new ilCheckboxGroupInputGUI($this->lng->txt("tst_instant_feedback"), "instant_feedback");
		$instant_feedback->addOption(new ilCheckboxOption($this->lng->txt("tst_instant_feedback_answer_specific"), 'instant_feedback_specific', ''));
		$instant_feedback->addOption(new ilCheckboxOption($this->lng->txt("tst_instant_feedback_answer_generic"), 'instant_feedback_generic', ''));
		$instant_feedback->addOption(new ilCheckboxOption($this->lng->txt("tst_instant_feedback_results"), 'instant_feedback_points', ''));
		$instant_feedback->addOption(new ilCheckboxOption($this->lng->txt("tst_instant_feedback_solution"), 'instant_feedback_solution', ''));
		$values = array();
		if ($this->object->getSpecificAnswerFeedback()) array_push($values, 'instant_feedback_specific');
		if ($this->object->getGenericAnswerFeedback()) array_push($values, 'instant_feedback_generic');
		if ($this->object->getAnswerFeedbackPoints()) array_push($values, 'instant_feedback_points');
		if ($this->object->getInstantFeedbackSolution()) array_push($values, 'instant_feedback_solution');
		$instant_feedback->setValue($values);
		$instant_feedback->setInfo($this->lng->txt("tst_instant_feedback_description"));
		$form->addItem($instant_feedback);

		// scoring properties
		$header_tr = new ilFormSectionHeaderGUI();
		$header_tr->setTitle($this->lng->txt("test_results"));
		$form->addItem($header_tr);
		
		// access to test results
		$results_access = new ilRadioGroupInputGUI($this->lng->txt("tst_results_access"), "results_access");
		$results_access->addOption(new ilRadioOption($this->lng->txt("tst_results_access_always"), 2, ''));
		$results_access->addOption(new ilRadioOption($this->lng->txt("tst_results_access_finished"), 1, ''));
		$results_access_date_limitation = new ilRadioOption($this->lng->txt("tst_results_access_date"), 3, '');
		$results_access->addOption($results_access_date_limitation);		
		$results_access->addOption(new ilRadioOption($this->lng->txt("tst_results_access_never"), 4, ''));
		$results_access->setValue($this->object->getScoreReporting());
		$results_access->setInfo($this->lng->txt("tst_results_access_description"));

		// access date
		$reporting_date = new ilDateTimeInputGUI('', 'reporting_date');
		$reporting_date->setShowDate(true);
		$reporting_date->setShowTime(true);
		if (strlen($this->object->getReportingDate()))
		{
			$reporting_date->setDate(new ilDateTime($this->object->getReportingDate(), IL_CAL_TIMESTAMP));
		}
		else
		{
			$reporting_date->setDate(new ilDateTime(time(), IL_CAL_UNIX));
		}
		$results_access_date_limitation->addSubItem($reporting_date);
		$form->addItem($results_access);

		$results_print_best_solution = new ilCheckboxInputGUI($this->lng->txt("tst_results_print_best_solution"), "print_bs_with_res");
		$results_print_best_solution->setInfo($this->lng->txt('tst_results_print_best_solution_info'));
		$results_print_best_solution->setValue(1);
		$results_print_best_solution->setChecked((bool) $this->object->isBestSolutionPrintedWithResult());
		$form->addItem($results_print_best_solution);

		// results presentation
		$results_presentation = new ilCheckboxGroupInputGUI($this->lng->txt("tst_results_presentation"), "results_presentation");
		$results_presentation->addOption(new ilCheckboxOption($this->lng->txt("tst_show_pass_details"), 'pass_details', ''));
		$results_presentation->addOption(new ilCheckboxOption($this->lng->txt("tst_show_solution_details"), 'solution_details', ''));
		$results_presentation->addOption(new ilCheckboxOption($this->lng->txt("tst_show_solution_printview"), 'solution_printview', ''));
		$results_presentation->addOption(new ilCheckboxOption($this->lng->txt("tst_show_solution_compare"), 'solution_compare', ''));
		$results_presentation->addOption(new ilCheckboxOption($this->lng->txt("tst_show_solution_feedback"), 'solution_feedback', ''));
		$results_presentation->addOption(new ilCheckboxOption($this->lng->txt("tst_show_solution_answers_only"), 'solution_answers_only', ''));
		$signatureOption = new ilCheckboxOption($this->lng->txt("tst_show_solution_signature"), 'solution_signature', '');
		$results_presentation->addOption($signatureOption);
		$results_presentation->addOption(new ilCheckboxOption($this->lng->txt("tst_show_solution_suggested"), 'solution_suggested', ''));
		$values = array();
		if ($this->object->getShowPassDetails()) array_push($values, 'pass_details');
		if ($this->object->getShowSolutionDetails()) array_push($values, 'solution_details');
		if ($this->object->getShowSolutionPrintview()) array_push($values, 'solution_printview');
		if ($this->object->getShowSolutionFeedback()) array_push($values, 'solution_feedback');
		if ($this->object->getShowSolutionAnswersOnly()) array_push($values, 'solution_answers_only');
		if ($this->object->getShowSolutionSignature()) array_push($values, 'solution_signature');
		if ($this->object->getShowSolutionSuggested()) array_push($values, 'solution_suggested');
		if ($this->object->getShowSolutionListComparison()) array_push($values, 'solution_compare');
		$results_presentation->setValue($values);
		$results_presentation->setInfo($this->lng->txt("tst_results_presentation_description"));
		if ($this->object->getAnonymity())
		{
			$signatureOption->setDisabled(true);
		}
		$form->addItem($results_presentation);

		// misc properties
		$header_misc = new ilFormSectionHeaderGUI();
		$header_misc->setTitle($this->lng->txt("misc"));
		$form->addItem($header_misc);
		
		// deletion of test results
		$passDeletion = new ilRadioGroupInputGUI($this->lng->txt("tst_pass_deletion"), "pass_deletion_allowed");
		$passDeletion->addOption(new ilRadioOption($this->lng->txt("tst_pass_deletion_not_allowed"), 0, ''));
		$passDeletion->addOption(new ilRadioOption($this->lng->txt("tst_pass_deletion_allowed"), 1, ''));
		$passDeletion->setValue($this->object->isPassDeletionAllowed());
		$form->addItem($passDeletion);
		
		// export settings
		$export_settings = new ilCheckboxGroupInputGUI($this->lng->txt("tst_export_settings"), "export_settings");
		$export_settings->addOption(new ilCheckboxOption($this->lng->txt("tst_exp_sc_short"), 'exp_sc_short', ''));
		$values = array();
		if ($this->object->getExportSettingsSingleChoiceShort()) array_push($values, 'exp_sc_short');
		$export_settings->setValue($values);
		$form->addItem($export_settings);
		
		
		if ($ilAccess->checkAccess("write", "", $_GET["ref_id"]) && $total == 0) $form->addCommandButton("saveScoring", $this->lng->txt("save"));
		if ($ilAccess->checkAccess("write", "", $_GET["ref_id"]) && $total != 0) $form->addCommandButton("confirmScoring", $this->lng->txt("save"));

		// remove items when using template
		if($template_settings)
		{
			foreach($template_settings as $id => $item)
			{
				if($item["hide"])
				{
					$form->removeItemByPostVar($id);
				}
			}
		}

                $errors = false;

		if ($save)
		{
			$errors = !$form->checkInput();
			$form->setValuesByPost();
			if ($errors) $checkonly = false;
		}
		
		if (!$checkonly) $this->tpl->setVariable("ADM_CONTENT", $form->getHTML());
		return $errors;
	}
	
	/**
	* download file
	*/
	function downloadFileObject()
	{
		$file = explode("_", $_GET["file_id"]);
		include_once("./Modules/File/classes/class.ilObjFile.php");
		$fileObj =& new ilObjFile($file[count($file) - 1], false);
		$fileObj->sendFile();
		exit;
	}
	
	/**
	* show fullscreen view
	*/
	function fullscreenObject()
	{
		include_once("./Modules/TestQuestionPool/classes/class.ilAssQuestionPageGUI.php");
		$page_gui = new ilAssQuestionPageGUI($_GET["pg_id"]);
		$page_gui->showMediaFullscreen();
		
	}

	/**
	* download source code paragraph
	*/
	function download_paragraphObject()
	{
		include_once("./Modules/TestQuestionPool/classes/class.ilAssQuestionPage.php");
		$pg_obj = new ilAssQuestionPage($_GET["pg_id"]);
		$pg_obj->send_paragraph ($_GET["par_id"], $_GET["downloadtitle"]);
		exit;
	}

	/**
	* Sets the filter for the question browser 
	*
	* Sets the filter for the question browser 
	*
	* @access	public
	*/
	function filterObject()
	{
		$this->questionBrowser();
	}

	/**
	* Resets the filter for the question browser 
	*
	* Resets the filter for the question browser 
	*
	* @access	public
	*/
	function resetFilterObject()
	{
		$this->questionBrowser();
	}

	/**
	* Called when the back button in the question browser was pressed 
	*
	* Called when the back button in the question browser was pressed 
	*
	* @access	public
	*/
	function backObject()
	{
		$this->ctrl->redirect($this, "questions");
	}
	
	/**
	* Creates a new questionpool and returns the reference id
	*
	* Creates a new questionpool and returns the reference id
	*
	* @return integer Reference id of the newly created questionpool
	* @access	public
	*/
	function createQuestionPool($name = "dummy", $description = "")
	{
		global $tree;
		$parent_ref = $tree->getParentId($this->object->getRefId());
		include_once "./Modules/TestQuestionPool/classes/class.ilObjQuestionPool.php";
		$qpl = new ilObjQuestionPool();
		$qpl->setType("qpl");
		$qpl->setTitle($name);
		$qpl->setDescription($description);
		$qpl->create();
		$qpl->createReference();
		$qpl->putInTree($parent_ref);
		$qpl->setPermissions($parent_ref);
		$qpl->setOnline(1); // must be online to be available
		$qpl->saveToDb();
		return $qpl->getRefId();
	}

	/**
	* Creates a form for random selection of questions
	*/
	public function randomselectObject()
	{
		global $ilUser;
		$this->getQuestionsSubTabs();
		$this->tpl->addBlockFile("ADM_CONTENT", "adm_content", "tpl.il_as_tst_random_select.html", "Modules/Test");
		$questionpools =& $this->object->getAvailableQuestionpools(FALSE, FALSE, FALSE, TRUE);
		$this->tpl->setCurrentBlock("option");
		$this->tpl->setVariable("VALUE_OPTION", "0");
		$this->tpl->setVariable("TEXT_OPTION", $this->lng->txt("all_available_question_pools"));
		$this->tpl->parseCurrentBlock();
		foreach ($questionpools as $key => $value)
		{
			$this->tpl->setCurrentBlock("option");
			$this->tpl->setVariable("VALUE_OPTION", $key);
			$this->tpl->setVariable("TEXT_OPTION", $value["title"]);
			$this->tpl->parseCurrentBlock();
		}
		$this->tpl->setCurrentBlock("hidden");
		$this->tpl->setVariable("HIDDEN_NAME", "sel_question_types");
		$this->tpl->setVariable("HIDDEN_VALUE", $_POST["sel_question_types"]);
		$this->tpl->parseCurrentBlock();
		$this->tpl->setCurrentBlock("adm_content");
		$this->tpl->setVariable("FORM_ACTION", $this->ctrl->getFormAction($this));
		$this->tpl->setVariable("TXT_QPL_SELECT", $this->lng->txt("tst_random_select_questionpool"));
		$this->tpl->setVariable("TXT_NR_OF_QUESTIONS", $this->lng->txt("tst_random_nr_of_questions"));
		$this->tpl->setVariable("BTN_SUBMIT", $this->lng->txt("submit"));
		$this->tpl->setVariable("BTN_CANCEL", $this->lng->txt("cancel"));
		$this->tpl->parseCurrentBlock();
	}
	
	/**
	* Cancels the form for random selection of questions
	*
	* Cancels the form for random selection of questions
	*
	* @access	public
	*/
	function cancelRandomSelectObject()
	{
		$this->ctrl->redirect($this, "questions");
	}
	
	/**
	* Offers a random selection for insertion in the test
	*
	* Offers a random selection for insertion in the test
	*
	* @access	public
	*/
	function createRandomSelectionObject()
	{
		$this->getQuestionsSubTabs();
		$question_array = $this->object->randomSelectQuestions($_POST["nr_of_questions"], $_POST["sel_qpl"]);
		$this->tpl->addBlockFile("ADM_CONTENT", "adm_content", "tpl.il_as_tst_random_question_offer.html", "Modules/Test");
		$color_class = array("tblrow1", "tblrow2");
		$counter = 0;
		$questionpools =& $this->object->getAvailableQuestionpools(true);
		include_once "./Modules/TestQuestionPool/classes/class.assQuestion.php";
		foreach ($question_array as $question_id)
		{
			$dataset = $this->object->getQuestionDataset($question_id);
			$this->tpl->setCurrentBlock("QTab");
			$this->tpl->setVariable("COLOR_CLASS", $color_class[$counter % 2]);
			$this->tpl->setVariable("QUESTION_TITLE", $dataset->title);
			$this->tpl->setVariable("QUESTION_COMMENT", $dataset->description);
			$this->tpl->setVariable("QUESTION_TYPE", assQuestion::_getQuestionTypeName($dataset->type_tag));
			$this->tpl->setVariable("QUESTION_AUTHOR", $dataset->author);
			$this->tpl->setVariable("QUESTION_POOL", $questionpools[$dataset->obj_fi]["title"]);
			$this->tpl->parseCurrentBlock();
			$counter++;
		}
		if (count($question_array) == 0)
		{
			$this->tpl->setCurrentBlock("Emptytable");
			$this->tpl->setVariable("TEXT_NO_QUESTIONS_AVAILABLE", $this->lng->txt("no_questions_available"));
			$this->tpl->parseCurrentBlock();
		}
			else
		{
			$this->tpl->setCurrentBlock("Selectionbuttons");
			$this->tpl->setVariable("BTN_YES", $this->lng->txt("random_accept_sample"));
			$this->tpl->setVariable("BTN_NO", $this->lng->txt("random_another_sample"));
			$this->tpl->parseCurrentBlock();
		}
		$chosen_questions = join($question_array, ",");
		$this->tpl->setCurrentBlock("adm_content");
		$this->tpl->setVariable("FORM_ACTION", $this->ctrl->getFormAction($this));
		$this->tpl->setVariable("QUESTION_TITLE", $this->lng->txt("tst_question_title"));
		$this->tpl->setVariable("QUESTION_COMMENT", $this->lng->txt("description"));
		$this->tpl->setVariable("QUESTION_TYPE", $this->lng->txt("tst_question_type"));
		$this->tpl->setVariable("QUESTION_AUTHOR", $this->lng->txt("author"));
		$this->tpl->setVariable("QUESTION_POOL", $this->lng->txt("qpl"));
		$this->tpl->setVariable("VALUE_CHOSEN_QUESTIONS", $chosen_questions);
		$this->tpl->setVariable("VALUE_QUESTIONPOOL_SELECTION", $_POST["sel_qpl"]);
		$this->tpl->setVariable("VALUE_NR_OF_QUESTIONS", $_POST["nr_of_questions"]);
		$this->tpl->setVariable("TEXT_QUESTION_OFFER", $this->lng->txt("tst_question_offer"));
		$this->tpl->setVariable("BTN_CANCEL", $this->lng->txt("cancel"));
		$this->tpl->parseCurrentBlock();
	}
	
	/**
	* Inserts a random selection into the test
	*
	* Inserts a random selection into the test
	*
	* @access	public
	*/
	function insertRandomSelectionObject()
	{
		$selected_array = split(",", $_POST["chosen_questions"]);
		if (!count($selected_array))
		{
			ilUtil::sendInfo($this->lng->txt("tst_insert_missing_question"));
		}
		else
		{
			$total = $this->object->evalTotalPersons();
			if ($total)
			{
				// the test was executed previously
				ilUtil::sendInfo(sprintf($this->lng->txt("tst_insert_questions_and_results"), $total));
			}
			else
			{
				ilUtil::sendInfo($this->lng->txt("tst_insert_questions"));
			}
			foreach ($selected_array as $key => $value) 
			{
				$this->object->insertQuestion( $this->testQuestionSetConfigFactory->getQuestionSetConfig(), $value );
			}
			$this->object->saveCompleteStatus( $this->testQuestionSetConfigFactory->getQuestionSetConfig() );
			ilUtil::sendSuccess($this->lng->txt("tst_questions_inserted"), true);
			$this->ctrl->redirect($this, "questions");
			return;
		}
	}
	
	function addQuestionpoolObject()
	{
		$this->randomQuestionsObject();
	}
	
	/**
	* Evaluates a posted random question form and saves the form data
	*
	* @return integer A positive value, if one of the required fields wasn't set, else 0
	* @access private
	*/
	function writeRandomQuestionInput($always = false)
	{
		$hasErrors = (!$always) ? $this->randomQuestionsObject(true) : false;
		if (!$hasErrors)
		{
			global $ilUser;
			$ilUser->setPref("tst_question_selection_mode_equal", ($_POST['chbQuestionSelectionMode']) ? 1 : 0);
			$ilUser->writePref("tst_question_selection_mode_equal", ($_POST['chbQuestionSelectionMode']) ? 1 : 0);
			$this->object->setRandomQuestionCount($_POST['total_questions']);
			if (is_array($_POST['source']['qpl']) && count(array_unique($_POST['source']['qpl'])) == count($_POST['source']['qpl']))
			{
				$data = array();
				include_once "./Modules/Test/classes/class.ilRandomTestData.php";
				foreach ($_POST['source']['qpl'] as $idx => $qpl)
				{
					array_push($data, new ilRandomTestData($_POST['source']['count'][$idx], $qpl));
				}
				$this->object->setRandomQuestionpoolData($data);
			}
			return 0;
		}
		return 1;
	}

	function saveRandomQuestionsObject()
	{
		if ($this->writeRandomQuestionInput() == 0)
		{
			$this->object->saveRandomQuestionCount($this->object->getRandomQuestionCount());
			$this->object->saveRandomQuestionpools();
			$this->object->saveCompleteStatus( $this->testQuestionSetConfigFactory->getQuestionSetConfig() );
			ilUtil::sendSuccess($this->lng->txt("msg_obj_modified"), true);
			$this->ctrl->redirect($this, 'randomQuestions');
		}
	}
		
	function addsourceObject()
	{
		$this->writeRandomQuestionInput(true);
		$position = key($_POST['cmd']['addsource']);
		$this->object->addRandomQuestionpoolData(0, 0, $position+1);
		$this->randomQuestionsObject();
	}
	
	function removesourceObject()
	{
		$this->writeRandomQuestionInput(true);
		$position = key($_POST['cmd']['removesource']);
		$this->object->removeRandomQuestionpoolData($position);
		$this->randomQuestionsObject();
	}

	function randomQuestionsObject()
	{
		global $ilUser;

		$total = $this->object->evalTotalPersons();
		$save = (strcmp($this->ctrl->getCmd(), "saveRandomQuestions") == 0) ? TRUE : FALSE;

		include_once("./Services/Form/classes/class.ilPropertyFormGUI.php");
		$form = new ilPropertyFormGUI();
		$form->setFormAction($this->ctrl->getFormAction($this, 'randomQuestions'));
		$form->setTitle($this->lng->txt('random_selection'));
		$form->setDescription($this->lng->txt('tst_select_random_questions'));
		$form->setMultipart(FALSE);
		$form->setTableWidth("100%");
		$form->setId("randomSelectionForm");

		// question selection
		$selection_mode = ($save) ? $_POST['chbQuestionSelectionMode'] : $ilUser->getPref("tst_question_selection_mode_equal");
		$question_selection = new ilCheckboxInputGUI($this->lng->txt("tst_question_selection"), "chbQuestionSelectionMode");
		$question_selection->setValue(1);
		$question_selection->setChecked($selection_mode);
		$question_selection->setOptionTitle($this->lng->txt('tst_question_selection_equal'));
		$question_selection->setInfo($this->lng->txt('tst_question_selection_description'));
		$question_selection->setRequired(false);
		$form->addItem($question_selection);
		
		// total amount of questions
		$total_questions = new ilNumberInputGUI($this->lng->txt('tst_total_questions'), 'total_questions');
		$total_questions->setValue($this->object->getRandomQuestionCount());
		$total_questions->setSize(3);
		$total_questions->setInfo($this->lng->txt('tst_total_questions_description'));
		$total_questions->setRequired(false);
		$form->addItem($total_questions);

		if ($total == 0)
		{
			$found_qpls = $this->object->getRandomQuestionpoolData();
			include_once "./Modules/Test/classes/class.ilRandomTestData.php";
			if (count($found_qpls) == 0)
			{
				array_push($found_qpls, new ilRandomTestData());
			}
			$available_qpl =& $this->object->getAvailableQuestionpools(TRUE, $selection_mode, FALSE, TRUE, TRUE);
			include_once './Modules/Test/classes/class.ilRandomTestInputGUI.php';
			$source = new ilRandomTestInputGUI($this->lng->txt('tst_random_questionpools'), 'source');
			$source->setUseEqualPointsOnly($selection_mode);
			$source->setRandomQuestionPools($available_qpl);
			$source->setUseQuestionCount((array_key_exists('total_questions', $_POST)) ? ($_POST['total_questions'] < 1) : ($this->object->getRandomQuestionCount() < 1));
			$source->setValues($found_qpls);
			$form->addItem($source);
		}
		else
		{
			$qpls = $this->object->getUsedRandomQuestionpools();
			include_once './Modules/Test/classes/class.ilRandomTestROInputGUI.php';
			$source = new ilRandomTestROInputGUI($this->lng->txt('tst_random_questionpools'), 'source');
			$source->setValues($qpls);
			$form->addItem($source);
		}

		if ($total == 0) $form->addCommandButton("saveRandomQuestions", $this->lng->txt("save"));
	
		$errors = false;
	
		if ($save)
		{
			$form->setValuesByPost();
			$errors = !$form->checkInput();
			if (!$errors)
			{
				// check total amount of questions
				if ($_POST['total_questions'] > 0)
				{
					$totalcount = 0;
					foreach ($_POST['source']['qpl'] as $idx => $qpl)
					{
						$totalcount += $available_qpl[$qpl]['count'];
					}
					if ($_POST['total_questions'] > $totalcount)
					{
						$total_questions->setAlert($this->lng->txt('msg_total_questions_too_high'));
						$errors = true;
					}
				}
			}
			if ($errors) $checkonly = false;
		}

		if (!$checkonly) $this->tpl->setVariable("ADM_CONTENT", $form->getHTML());
		return $errors;
	}
	
	function saveQuestionSelectionModeObject()
	{
		global $ilUser;
		if ($_POST["chbQuestionSelectionMode"])
		{
			$ilUser->setPref("tst_question_selection_mode_equal", 1);
			$ilUser->writePref("tst_question_selection_mode_equal", 1);
		}
		else
		{
			$ilUser->setPref("tst_question_selection_mode_equal", 0);
			$ilUser->writePref("tst_question_selection_mode_equal", 0);
		}
		$this->randomQuestionsObject();
	}

	function browseForQuestionsObject()
	{
		$this->questionBrowser();
	}
	
	/**
	* Called when a new question should be created from a test after confirmation
	*
	* Called when a new question should be created from a test after confirmation
	*
	* @access	public
	*/
	function executeCreateQuestionObject()
	{
		$qpl_ref_id = $_REQUEST["sel_qpl"];

		$qpl_mode = $_REQUEST['usage'];

		if (!$qpl_mode || ($qpl_mode == 2 && strcmp($_REQUEST["txt_qpl"], "") == 0) || ($qpl_mode == 3 && strcmp($qpl_ref_id, "") == 0))
		//if ((strcmp($_REQUEST["txt_qpl"], "") == 0) && (strcmp($qpl_ref_id, "") == 0))
		{
			ilUtil::sendInfo($this->lng->txt("questionpool_not_entered"));
			$this->createQuestionObject();
			return;
		}
		else
		{
			$_SESSION["test_id"] = $this->object->getRefId();
			if ($qpl_mode == 2 && strcmp($_REQUEST["txt_qpl"], "") != 0)
			{
				// create a new question pool and return the reference id
				$qpl_ref_id = $this->createQuestionPool($_REQUEST["txt_qpl"]);
			}
			else if ($qpl_mode == 1)
			{
			    $qpl_ref_id = $_GET["ref_id"];
			}

			include_once "./Modules/TestQuestionPool/classes/class.ilObjQuestionPoolGUI.php";

			$baselink = "ilias.php?baseClass=ilObjQuestionPoolGUI&ref_id=" . $qpl_ref_id . "&cmd=createQuestionForTest&test_ref_id=".$_GET["ref_id"]."&calling_test=".$_GET["ref_id"]."&sel_question_types=" . $_REQUEST["sel_question_types"];

			if ($_REQUEST['prev_qid']) {
			    $baselink .= '&prev_qid=' . $_REQUEST['prev_qid'];
			}
			if ($_REQUEST['test_express_mode']) {
			    $baselink .= '&test_express_mode=1';
			}
			
			if( isset($_REQUEST['add_quest_cont_edit_mode']) )
			{
				$baselink = ilUtil::appendUrlParameterString(
						$baselink, "add_quest_cont_edit_mode={$_REQUEST['add_quest_cont_edit_mode']}", false
				);
			}
			
#var_dump($_REQUEST['prev_qid']);
			ilUtil::redirect($baselink);
			
			exit();
		}
	}

	/**
	* Called when the creation of a new question is cancelled
	*
	* Called when the creation of a new question is cancelled
	*
	* @access	public
	*/
	function cancelCreateQuestionObject()
	{
		$this->ctrl->redirect($this, "questions");
	}

	/**
	* Called when a new question should be created from a test
	*
	* Called when a new question should be created from a test
	*
	* @access	public
	*/
	function createQuestionObject()
	{
		global $ilUser;
		$this->getQuestionsSubTabs();
		//$this->tpl->addBlockFile("ADM_CONTENT", "adm_content", "tpl.il_as_tst_qpl_select.html", "Modules/Test");
		$questionpools =& $this->object->getAvailableQuestionpools(FALSE, FALSE, FALSE, TRUE, FALSE, "write");
		
		if ($this->object->getPoolUsage()) {
		    global $lng, $ilCtrl, $tpl;

		    include_once "Services/Form/classes/class.ilPropertyFormGUI.php";

		    $form = new ilPropertyFormGUI();
		    $form->setFormAction($ilCtrl->getFormAction($this, "executeCreateQuestion"));
		    $form->setTitle($lng->txt("ass_create_question"));
		    include_once 'Modules/TestQuestionPool/classes/class.ilObjQuestionPool.php';


		    $hidden = new ilHiddenInputGUI('sel_question_types');
		    $hidden->setValue($_REQUEST["sel_question_types"]);
		    $form->addItem($hidden);

			// content editing mode
			if( ilObjAssessmentFolder::isAdditionalQuestionContentEditingModePageObjectEnabled() )
			{
				$ri = new ilRadioGroupInputGUI($lng->txt("tst_add_quest_cont_edit_mode"), "add_quest_cont_edit_mode");

				$ri->addOption(new ilRadioOption(
						$lng->txt('tst_add_quest_cont_edit_mode_default'),
						assQuestion::ADDITIONAL_CONTENT_EDITING_MODE_DEFAULT
				));

				$ri->addOption(new ilRadioOption(
						$lng->txt('tst_add_quest_cont_edit_mode_page_object'),
						assQuestion::ADDITIONAL_CONTENT_EDITING_MODE_PAGE_OBJECT
				));
				
				$ri->setValue(assQuestion::ADDITIONAL_CONTENT_EDITING_MODE_DEFAULT);

				$form->addItem($ri, true);
			}
			else
			{
				$hi = new ilHiddenInputGUI("question_content_editing_type");
				$hi->setValue(assQuestion::ADDITIONAL_CONTENT_EDITING_MODE_DEFAULT);
				$form->addItem($hi, true);
			}
			
		    // use pool
		    $usage = new ilRadioGroupInputGUI($this->lng->txt("assessment_pool_selection"), "usage");
		    $usage->setRequired(true);
		    $no_pool = new ilRadioOption($this->lng->txt("assessment_no_pool"), 1);
		    $usage->addOption($no_pool);
		    $existing_pool = new ilRadioOption($this->lng->txt("assessment_existing_pool"), 3);
		    $usage->addOption($existing_pool);
		    $new_pool = new ilRadioOption($this->lng->txt("assessment_new_pool"), 2);
		    $usage->addOption($new_pool);
		    $form->addItem($usage);

		    $usage->setValue(1);

		    $questionpools = ilObjQuestionPool::_getAvailableQuestionpools(FALSE, FALSE, TRUE, FALSE, FALSE, "write");
		    $pools_data = array();
		    foreach($questionpools as $key => $p) {
			$pools_data[$key] = $p['title'];
		    }
		    $pools = new ilSelectInputGUI($this->lng->txt("select_questionpool"), "sel_qpl");
		    $pools->setOptions($pools_data);
		    $existing_pool->addSubItem($pools);


			$this->lng->loadLanguageModule('rbac');
		    $name = new ilTextInputGUI($this->lng->txt("rbac_create_qpl"), "txt_qpl");
		    $name->setSize(50);
		    $name->setMaxLength(50);
		    $new_pool->addSubItem($name);

		    $form->addCommandButton("executeCreateQuestion", $lng->txt("submit"));
		    $form->addCommandButton("cancelCreateQuestion", $lng->txt("cancel"));

		    return $this->tpl->setVariable('ADM_CONTENT', $form->getHTML());

		}
		else {
		    global $ilCtrl;

		    $ilCtrl->setParameterByClass('iltestexpresspageobjectgui', 'sel_question_types', $_REQUEST["sel_question_types"]);
		    $link = $ilCtrl->getLinkTargetByClass('iltestexpresspageobjectgui', 'handleToolbarCommand','',false,false);
		    ilUtil::redirect($link);
		}
	}

	/**
	 * Remove questions from the test after confirmation
	 */
	public function confirmRemoveQuestionsObject()
	{
		$checked_questions = $_POST["q_id"];

		$questions = $this->object->getQuestionTitlesAndIndexes();
		$deleted   = array();
		foreach((array)$checked_questions as $value)
		{
			$this->object->removeQuestion($value);
			$deleted[] = $value;
		}

		$this->object->saveCompleteStatus( $this->testQuestionSetConfigFactory->getQuestionSetConfig() );
		
		ilUtil::sendSuccess($this->lng->txt("tst_questions_removed"));

		if($_REQUEST['test_express_mode'])
		{
			$prev        = null;
			$return_to   = null;
			$deleted_tmp = $deleted;
			$first       = array_shift($deleted_tmp);
			foreach((array)$questions as $key => $value)
			{
				if(!in_array($key, $deleted))
				{
					$prev = $key;
					if(!$first)
					{
						$return_to = $prev;
						break;
					}
					else continue;
				}
				else if($key == $first)
				{
					if($prev)
					{
						$return_to = $prev;
						break;
					}
					$first = array_shift($deleted_tmp);
				}
			}

			if(
				count($questions) == count($checked_questions) ||
				!$return_to
			)
			{
				$this->ctrl->setParameter($this, 'q_id', '');
				$this->ctrl->redirect($this, 'showPage');
			}

			$this->ctrl->setParameter($this, 'q_id', $return_to);
			$this->ctrl->redirect($this, "showPage");
		}
		else
		{
			$this->ctrl->setParameter($this, 'q_id', '');
			$this->ctrl->redirect($this, 'questions');
		}
	}
	
	/**
	* Cancels the removal of questions from the test
	*
	* Cancels the removal of questions from the test
	*
	* @access	public
	*/
	function cancelRemoveQuestionsObject()
	{
	    	if ($_REQUEST['test_express_mode']) {
		    $this->ctrl->setParameter($this, 'q_id', $_REQUEST['q_id']);
		    $this->ctrl->redirect($this, "showPage");
		}
		else {
		    $this->ctrl->redirect($this, "questions");
		}
	}
	
	/**
	* Displays a form to confirm the removal of questions from the test
	*
	* Displays a form to confirm the removal of questions from the test
	*
	* @access	public
	*/
	function removeQuestionsForm($checked_questions)
	{		
		$total = $this->object->evalTotalPersons();
		if ($total) 
		{
			// the test was executed previously
			$question = sprintf($this->lng->txt("tst_remove_questions_and_results"), $total);
		} 
		else 
		{
			if (count($checked_questions) == 1)
			{
				$question = $this->lng->txt("tst_remove_question");
			}
			else
			{
				$question = $this->lng->txt("tst_remove_questions");
			}
		}
				
		include_once("./Services/Utilities/classes/class.ilConfirmationGUI.php");
		$cgui = new ilConfirmationGUI();
		$cgui->setHeaderText($question);

		$this->ctrl->saveParameter($this, 'test_express_mode');
		$this->ctrl->saveParameter($this, 'q_id');
		
		$cgui->setFormAction($this->ctrl->getFormAction($this));
		$cgui->setCancel($this->lng->txt("cancel"), "cancelRemoveQuestions");
		$cgui->setConfirm($this->lng->txt("confirm"), "confirmRemoveQuestions");
								
		include_once "./Modules/TestQuestionPool/classes/class.assQuestion.php";
		$removablequestions =& $this->object->getTestQuestions();				
		if (count($removablequestions))
		{
			foreach ($removablequestions as $data)
			{
				if (in_array($data["question_id"], $checked_questions))
				{
					$txt = $data["title"]." (".assQuestion::_getQuestionTypeName($data["type_tag"]).")";
					$txt .= ' ['. $this->lng->txt('question_id_short') . ': ' . $data['question_id']  . ']';
					
					if($data["description"])
					{
						$txt .= "<div class=\"small\">".$data["description"]."</div>";
					}
					
					$cgui->addItem("q_id[]", $data["question_id"], $txt);
				}
			}		
		}
		
		$this->tpl->setContent($cgui->getHTML());		
	}

	/**
	* Called when a selection of questions should be removed from the test
	*
	* Called when a selection of questions should be removed from the test
	*
	* @access	public
	*/
	function removeQuestionsObject()
	{
		$this->getQuestionsSubTabs();
		$checked_questions = $_REQUEST["q_id"];
		if (!is_array($checked_questions) && $checked_questions) {
		    $checked_questions = array($checked_questions);
		}
		if (count($checked_questions) > 0) 
		{			
			$this->removeQuestionsForm($checked_questions);
			return;
		} 
		elseif (count($checked_questions) == 0) 
		{
			ilUtil::sendInfo($this->lng->txt("tst_no_question_selected_for_removal"), true);
			$this->ctrl->redirect($this, "questions");
		}
	}
	
	/**
	* Marks selected questions for moving
	*/
	function moveQuestionsObject()
	{
		$selected_questions = NULL;
		$selected_questions = $_POST['q_id'];
		if (is_array($selected_questions))
		{
			$_SESSION['tst_qst_move_' . $this->object->getTestId()] = $_POST['q_id'];
			ilUtil::sendSuccess($this->lng->txt("msg_selected_for_move"), true);
		}
		else
		{
			ilUtil::sendFailure($this->lng->txt('no_selection_for_move'), TRUE);
		}
		$this->ctrl->redirect($this, 'questions');
	}
	
	/**
	* Insert checked questions before the actual selection
	*/
	public function insertQuestionsBeforeObject()
	{
		// get all questions to move
		$move_questions = $_SESSION['tst_qst_move_' . $this->object->getTestId()];

		if (count($_POST['q_id']) == 0)
		{
			ilUtil::sendFailure($this->lng->txt("no_target_selected_for_move"), true);
			$this->ctrl->redirect($this, 'questions');
		}
		if (count($_POST['q_id']) > 1)
		{
			ilUtil::sendFailure($this->lng->txt("too_many_targets_selected_for_move"), true);
			$this->ctrl->redirect($this, 'questions');
		}
		$insert_mode = 0;
		$this->object->moveQuestions($_SESSION['tst_qst_move_' . $this->object->getTestId()], $_POST['q_id'][0], $insert_mode);
		ilUtil::sendSuccess($this->lng->txt("msg_questions_moved"), true);
		unset($_SESSION['tst_qst_move_' . $this->object->getTestId()]);
		$this->ctrl->redirect($this, "questions");
	}
	
	/**
	* Insert checked questions after the actual selection
	*/
	public function insertQuestionsAfterObject()
	{
		// get all questions to move
		$move_questions = $_SESSION['tst_qst_move_' . $this->object->getTestId()];
		if (count($_POST['q_id']) == 0)
		{
			ilUtil::sendFailure($this->lng->txt("no_target_selected_for_move"), true);
			$this->ctrl->redirect($this, 'questions');
		}
		if (count($_POST['q_id']) > 1)
		{
			ilUtil::sendFailure($this->lng->txt("too_many_targets_selected_for_move"), true);
			$this->ctrl->redirect($this, 'questions');
		}
		$insert_mode = 1;
		$this->object->moveQuestions($_SESSION['tst_qst_move_' . $this->object->getTestId()], $_POST['q_id'][0], $insert_mode);
		ilUtil::sendSuccess($this->lng->txt("msg_questions_moved"), true);
		unset($_SESSION['tst_qst_move_' . $this->object->getTestId()]);
		$this->ctrl->redirect($this, "questions");
	}
	
	/**
	* Insert questions from the questionbrowser into the test 
	*
	* @access	public
	*/
	function insertQuestionsObject()
	{
		$selected_array = (is_array($_POST['q_id'])) ? $_POST['q_id'] : array();
		if (!count($selected_array))
		{
			ilUtil::sendInfo($this->lng->txt("tst_insert_missing_question"), true);
			$this->ctrl->redirect($this, "browseForQuestions");
		}
		else
		{
			include_once "./Modules/TestQuestionPool/classes/class.assQuestion.php";
			$manscoring = FALSE;
			foreach ($selected_array as $key => $value) 
			{
				$this->object->insertQuestion( $this->testQuestionSetConfigFactory->getQuestionSetConfig(), $value );
				if (!$manscoring)
				{
					$manscoring = $manscoring | assQuestion::_needsManualScoring($value);
				}
			}
			$this->object->saveCompleteStatus( $this->testQuestionSetConfigFactory->getQuestionSetConfig() );
			if ($manscoring)
			{
				ilUtil::sendInfo($this->lng->txt("manscoring_hint"), TRUE);
			}
			else
			{
				ilUtil::sendSuccess($this->lng->txt("tst_questions_inserted"), TRUE);
			}
			$this->ctrl->redirect($this, "questions");
			return;
		}
	}

	public function filterAvailableQuestionsObject()
	{
		include_once "./Modules/Test/classes/tables/class.ilTestQuestionBrowserTableGUI.php";
		$table_gui = new ilTestQuestionBrowserTableGUI($this, 'browseForQuestions');
		$table_gui->writeFilterToSession();
		$this->ctrl->redirect($this, "browseForQuestions");
	}
	
	public function resetfilterAvailableQuestionsObject()
	{
		include_once "./Modules/Test/classes/tables/class.ilTestQuestionBrowserTableGUI.php";
		$table_gui = new ilTestQuestionBrowserTableGUI($this, 'browseForQuestions');
		$table_gui->resetFilter();
		$this->ctrl->redirect($this, "browseForQuestions");
	}
	
	/**
	* Creates a form to select questions from questionpools to insert the questions into the test 
	*
	* @access	public
	*/
	function questionBrowser()
	{
		global $ilAccess;

		$this->ctrl->setParameterByClass(get_class($this), "browse", "1");

		include_once "./Modules/Test/classes/tables/class.ilTestQuestionBrowserTableGUI.php";
		$table_gui = new ilTestQuestionBrowserTableGUI($this, 'browseForQuestions', (($ilAccess->checkAccess("write", "", $this->ref_id) ? true : false)));
		$arrFilter = array();
		foreach ($table_gui->getFilterItems() as $item)
		{
			if ($item->getValue() !== false)
			{
				$arrFilter[$item->getPostVar()] = $item->getValue();
			}
		}
		$data = $this->object->getAvailableQuestions($arrFilter, 1);
		$table_gui->setData($data);
		$this->tpl->setVariable('ADM_CONTENT', $table_gui->getHTML());	
	}

	function questionsObject()
	{
		global $ilAccess, $ilTabs;

		$ilTabs->activateTab('assQuestions');

		if (!$ilAccess->checkAccess("write", "", $this->ref_id)) 
		{
			// allow only write access
			ilUtil::sendInfo($this->lng->txt("cannot_edit_test"), true);
			$this->ctrl->redirect($this, "infoScreen");
		}

		if ($_GET['browse'])
		{
			return $this->questionbrowser();
		}

		$this->getQuestionsSubTabs();
		if ($this->object->isRandomTest())
		{
			$this->randomQuestionsObject();
			return;
		}
		
		if ($_GET["eqid"] && $_GET["eqpl"])
		{
			ilUtil::redirect("ilias.php?baseClass=ilObjQuestionPoolGUI&ref_id=" . $_GET["eqpl"] . "&cmd=editQuestionForTest&calling_test=".$_GET["ref_id"]."&q_id=" . $_GET["eqid"]);
		}
		
		if ($_GET["up"] > 0)
		{
			$this->object->questionMoveUp($_GET["up"]);
		}
		if ($_GET["down"] > 0)
		{
			$this->object->questionMoveDown($_GET["down"]);
		}

		if ($_GET["add"])
		{
			$selected_array = array();
			array_push($selected_array, $_GET["add"]);
			$total = $this->object->evalTotalPersons();
			if ($total)
			{
				// the test was executed previously
				ilUtil::sendInfo(sprintf($this->lng->txt("tst_insert_questions_and_results"), $total));
			}
			else
			{
				ilUtil::sendInfo($this->lng->txt("tst_insert_questions"));
			}
			$this->insertQuestions($selected_array);
			return;
		}

		$this->tpl->addBlockFile("ADM_CONTENT", "adm_content", "tpl.il_as_tst_questions.html", "Modules/Test");

		$total = $this->object->evalTotalPersons();
		if ($ilAccess->checkAccess("write", "", $this->ref_id))
		{
			if($total != 0)
			{
				$link = $this->ctrl->getLinkTarget($this, "participants");
				$link = "<a href=\"".$link."\">".$this->lng->txt("test_has_datasets_warning_page_view_link")."</a>";
				ilUtil::sendInfo($this->lng->txt("test_has_datasets_warning_page_view")." ".$link);
			}
			else {
				global $ilToolbar;

				$qtypes = array();
				include_once "./Modules/TestQuestionPool/classes/class.ilObjQuestionPool.php";
				foreach (ilObjQuestionPool::_getQuestionTypes(false, true) as $trans => $data)
				{
					$qtypes[$data['type_tag']] = $trans;
				}
				$ilToolbar->setFormAction($this->ctrl->getFormAction($this));
				include_once "Services/Form/classes/class.ilPropertyFormGUI.php";
				$types = new ilSelectInputGUI($this->lng->txt("create_new"), "sel_question_types");
				$types->setOptions($qtypes);

				$ilToolbar->addInputItem($types);
				$ilToolbar->addFormButton($this->lng->txt("ass_create_question"), "createQuestion");

				if ($this->object->getPoolUsage()) {
					$ilToolbar->addSeparator();
					$ilToolbar->addButton($this->lng->txt("tst_browse_for_questions"), $this->ctrl->getLinkTarget($this, 'browseForQuestions'));
				}

				$ilToolbar->addSeparator();
				$ilToolbar->addButton($this->lng->txt("random_selection"), $this->ctrl->getLinkTarget($this, "randomselect"));


				global $ilAccess, $ilUser, $lng, $ilCtrl;
				$online_access = false;
				if ($this->object->getFixedParticipants())
				{
					include_once "./Modules/Test/classes/class.ilObjTestAccess.php";
					$online_access_result = ilObjTestAccess::_lookupOnlineTestAccess($this->object->getId(), $ilUser->getId());
					if ($online_access_result === true)
					{
						$online_access = true;
					}
				}

				if( $this->object->isOnline() && $this->object->isComplete( $this->testQuestionSetConfigFactory->getQuestionSetConfig() ) )
				{
					if ((!$this->object->getFixedParticipants() || $online_access) && $ilAccess->checkAccess("read", "", $this->ref_id))
					{
						$testSession = $this->testSessionFactory->getSession();
						
						$executable = $this->object->isExecutable($testSession, $ilUser->getId(), $allowPassIncrease = TRUE);
						
						if ($executable["executable"])
						{
							if ($testSession->getActiveId() > 0)
							{
								$ilToolbar->addSeparator();
								$ilToolbar->addButton($lng->txt('tst_resume_test'), $ilCtrl->getLinkTargetByClass('iltestoutputgui', 'resume'));
							}
							else
							{
								$ilToolbar->addSeparator();
								$ilToolbar->addButton($lng->txt('tst_start_test'), $ilCtrl->getLinkTargetByClass('iltestoutputgui', 'startTest'));
							}
						}
					}
				}


			}
		}

		$this->tpl->setCurrentBlock("adm_content");
		include_once "./Modules/Test/classes/tables/class.ilTestQuestionsTableGUI.php";
		$checked_move = is_array($_SESSION['tst_qst_move_' . $this->object->getTestId()]) && (count($_SESSION['tst_qst_move_' . $this->object->getTestId()]));
		$table_gui = new ilTestQuestionsTableGUI($this, 'questions', (($ilAccess->checkAccess("write", "", $this->ref_id) ? true : false)), $checked_move, $total);
		$data = $this->object->getTestQuestions();
		$table_gui->setData($data);
		$this->tpl->setVariable('QUESTIONBROWSER', $table_gui->getHTML());	
		$this->tpl->setVariable("ACTION_QUESTION_FORM", $this->ctrl->getFormAction($this));
		$this->tpl->parseCurrentBlock();
	}

	function takenObject() {
	}
	
	/**
	* Add a new mark step to the tests marks
	*
	* Add a new mark step to the tests marks
	*
	* @access	public
	*/
	function addMarkStepObject()
	{
		$this->saveMarkSchemaFormData();
		$this->object->mark_schema->addMarkStep();
		$this->marksObject();
	}

	/**
	* Save the mark schema POST data when the form was submitted
	*
	* Save the mark schema POST data when the form was submitted
	*
	* @access	public
	*/
	function saveMarkSchemaFormData()
	{
		$this->object->mark_schema->flush();
		foreach ($_POST as $key => $value) {
			if (preg_match("/mark_short_(\d+)/", $key, $matches)) 
			{
				$this->object->mark_schema->addMarkStep(ilUtil::stripSlashes($_POST["mark_short_$matches[1]"]), ilUtil::stripSlashes($_POST["mark_official_$matches[1]"]), ilUtil::stripSlashes($_POST["mark_percentage_$matches[1]"]), ilUtil::stripSlashes($_POST["passed_$matches[1]"]));
			}
		}
		$this->object->ects_grades["A"] = $_POST["ects_grade_a"];
		$this->object->ects_grades["B"] = $_POST["ects_grade_b"];
		$this->object->ects_grades["C"] = $_POST["ects_grade_c"];
		$this->object->ects_grades["D"] = $_POST["ects_grade_d"];
		$this->object->ects_grades["E"] = $_POST["ects_grade_e"];
		if ($_POST["chbUseFX"])
		{
			$this->object->ects_fx = $_POST["percentFX"];
		}
		else
		{
			$this->object->ects_fx = "";
		}
		$this->object->ects_output = $_POST["chbECTS"];
	}
	
	/**
	* Add a simple mark schema to the tests marks
	*
	* Add a simple mark schema to the tests marks
	*
	* @access	public
	*/
	function addSimpleMarkSchemaObject()
	{
		$this->object->mark_schema->createSimpleSchema($this->lng->txt("failed_short"), $this->lng->txt("failed_official"), 0, 0, $this->lng->txt("passed_short"), $this->lng->txt("passed_official"), 50, 1);
		$this->marksObject();
	}
	
	/**
	* Delete selected mark steps
	*
	* Delete selected mark steps
	*
	* @access	public
	*/
	function deleteMarkStepsObject()
	{
		$this->saveMarkSchemaFormData();
		$delete_mark_steps = array();
		foreach ($_POST as $key => $value) {
			if (preg_match("/cb_(\d+)/", $key, $matches)) {
				array_push($delete_mark_steps, $matches[1]);
			}
		}
		if (count($delete_mark_steps)) {
			$this->object->mark_schema->deleteMarkSteps($delete_mark_steps);
		} else {
			ilUtil::sendInfo($this->lng->txt("tst_delete_missing_mark"));
		}
		$this->marksObject();
	}

	/**
	* Cancel the mark schema form and return to the properties form
	*
	* Cancel the mark schema form and return to the properties form
	*
	* @access	public
	*/
	function cancelMarksObject()
	{
		$this->ctrl->redirect($this, "marks");
	}
	
	/**
	* Save the mark schema
	*
	* Save the mark schema
	*
	* @access	public
	*/
	function saveMarksObject()
	{
		try
		{
			$this->saveMarkSchemaFormData();
			$mark_check = $this->object->checkMarks();
		}
		catch (Exception $ex)
		{
			$mark_check = $this->lng->txt('mark_schema_invalid');
		}
		
		if ($mark_check !== true)
		{
			ilUtil::sendFailure($this->lng->txt($mark_check));
		}
		elseif ($_POST["chbECTS"] && ((strcmp($_POST["ects_grade_a"], "") == 0) or (strcmp($_POST["ects_grade_b"], "") == 0) or (strcmp($_POST["ects_grade_c"], "") == 0) or (strcmp($_POST["ects_grade_d"], "") == 0) or (strcmp($_POST["ects_grade_e"], "") == 0)))
		{
			ilUtil::sendInfo($this->lng->txt("ects_fill_out_all_values"), true);
		}
		elseif (($_POST["ects_grade_a"] > 100) or ($_POST["ects_grade_a"] < 0))
		{
			ilUtil::sendInfo($this->lng->txt("ects_range_error_a"), true);
		}
		elseif (($_POST["ects_grade_b"] > 100) or ($_POST["ects_grade_b"] < 0))
		{
			ilUtil::sendInfo($this->lng->txt("ects_range_error_b"), true);
		}
		elseif (($_POST["ects_grade_c"] > 100) or ($_POST["ects_grade_c"] < 0))
		{
			ilUtil::sendInfo($this->lng->txt("ects_range_error_c"), true);
		}
		elseif (($_POST["ects_grade_d"] > 100) or ($_POST["ects_grade_d"] < 0))
		{
			ilUtil::sendInfo($this->lng->txt("ects_range_error_d"), true);
		}
		elseif (($_POST["ects_grade_e"] > 100) or ($_POST["ects_grade_e"] < 0))
		{
			ilUtil::sendInfo($this->lng->txt("ects_range_error_e"), true);
		}
		else 
		{
			$this->object->mark_schema->saveToDb($this->object->getTestId());
			$this->object->saveCompleteStatus( $this->testQuestionSetConfigFactory->getQuestionSetConfig() );
			if ($this->object->getReportingDate())
			{
				$fxpercent = "";
				if ($_POST["chbUseFX"])
				{
					$fxpercent = ilUtil::stripSlashes($_POST["percentFX"]);
				}
				$this->object->saveECTSStatus($_POST["chbECTS"], $fxpercent, ilUtil::stripSlashes($this->object->ects_grades["A"]), ilUtil::stripSlashes($this->object->ects_grades["B"]), ilUtil::stripSlashes($this->object->ects_grades["C"]), ilUtil::stripSlashes($this->object->ects_grades["D"]), ilUtil::stripSlashes($this->object->ects_grades["E"]));
			}
			ilUtil::sendSuccess($this->lng->txt("msg_obj_modified"), true);
		}
		$this->marksObject();
	}
	
	function marksObject() 
	{
		global $ilAccess;
		if (!$ilAccess->checkAccess("write", "", $this->ref_id)) 
		{
			// allow only write access
			ilUtil::sendInfo($this->lng->txt("cannot_edit_test"), true);
			$this->ctrl->redirect($this, "infoScreen");
		}

		if (!$this->object->canEditMarks())
		{
			ilUtil::sendInfo($this->lng->txt("cannot_edit_marks"));
		}
		
		$this->object->mark_schema->sort();
	
		$this->tpl->addBlockFile("ADM_CONTENT", "adm_content", "tpl.il_as_tst_marks.html", "Modules/Test");
		$marks = $this->object->mark_schema->mark_steps;
		$rows = array("tblrow1", "tblrow2");
		$counter = 0;
		foreach ($marks as $key => $value)
		{
			$this->tpl->setCurrentBlock("markrow");
			$this->tpl->setVariable("MARK_SHORT", $value->getShortName());
			$this->tpl->setVariable("MARK_OFFICIAL", $value->getOfficialName());
			$this->tpl->setVariable("MARK_PERCENTAGE", sprintf("%.2f", $value->getMinimumLevel()));
			$this->tpl->setVariable("MARK_PASSED", strtolower($this->lng->txt("tst_mark_passed")));
			$this->tpl->setVariable("MARK_ID", "$key");
			$this->tpl->setVariable("ROW_CLASS", $rows[$counter % 2]);

			if( $value->getPassed() )
			{
				$this->tpl->setVariable("MARK_PASSED_CHECKED", " checked=\"checked\"");
			}
			
			$this->tpl->parseCurrentBlock();
			$counter++;
		}
		if (count($marks) == 0) 
		{
			$this->tpl->setCurrentBlock("Emptyrow");
			$this->tpl->setVariable("EMPTY_ROW", $this->lng->txt("tst_no_marks_defined"));
			$this->tpl->setVariable("ROW_CLASS", $rows[$counter % 2]);
			$this->tpl->parseCurrentBlock();
		} 
		else 
		{
			if ($ilAccess->checkAccess("write", "", $this->ref_id) && $this->object->canEditMarks()) 
			{
				$this->tpl->setCurrentBlock("selectall");
				$counter++;
				$this->tpl->setVariable("ROW_CLASS", $rows[$counter % 2]);
				$this->tpl->setVariable("SELECT_ALL", $this->lng->txt("select_all"));
				$this->tpl->parseCurrentBlock();
				$this->tpl->setCurrentBlock("Footer");
				$this->tpl->setVariable("ARROW", "<img src=\"" . ilUtil::getImagePath("arrow_downright.png") . "\" alt=\"".$this->lng->txt("arrow_downright")."\"/>");
				$this->tpl->setVariable("BUTTON_EDIT", $this->lng->txt("edit"));
				$this->tpl->setVariable("BUTTON_DELETE", $this->lng->txt("delete"));
				$this->tpl->parseCurrentBlock();
			}
		}
		
		if ($this->object->getReportingDate())
		{
			$this->tpl->setCurrentBlock("ects");
			if ($this->object->ects_output)
			{
				$this->tpl->setVariable("CHECKED_ECTS", " checked=\"checked\"");
			}
			$this->tpl->setVariable("TEXT_OUTPUT_ECTS_GRADES", $this->lng->txt("ects_output_of_ects_grades"));
			$this->tpl->setVariable("TEXT_ALLOW_ECTS_GRADES", $this->lng->txt("ects_allow_ects_grades"));
			$this->tpl->setVariable("TEXT_USE_FX", $this->lng->txt("ects_use_fx_grade"));
			if (preg_match("/\d+/", $this->object->ects_fx))
			{
				$this->tpl->setVariable("CHECKED_FX", " checked=\"checked\"");
				$this->tpl->setVariable("VALUE_PERCENT_FX", sprintf("value=\"%s\" ", $this->object->ects_fx));
			}
			$this->tpl->setVariable("TEXT_PERCENT", $this->lng->txt("ects_use_fx_grade_part2"));
			$this->tpl->setVariable("ECTS_GRADE", $this->lng->txt("ects_grade"));
			$this->tpl->setVariable("PERCENTILE", $this->lng->txt("percentile"));
			$this->tpl->setVariable("ECTS_GRADE_A", "A - " . $this->lng->txt("ects_grade_a_short"));
			$this->tpl->setVariable("VALUE_GRADE_A", $this->object->ects_grades["A"]);
			$this->tpl->setVariable("ECTS_GRADE_B", "B - " . $this->lng->txt("ects_grade_b_short"));
			$this->tpl->setVariable("VALUE_GRADE_B", $this->object->ects_grades["B"]);
			$this->tpl->setVariable("ECTS_GRADE_C", "C - " . $this->lng->txt("ects_grade_c_short"));
			$this->tpl->setVariable("VALUE_GRADE_C", $this->object->ects_grades["C"]);
			$this->tpl->setVariable("ECTS_GRADE_D", "D - " . $this->lng->txt("ects_grade_d_short"));
			$this->tpl->setVariable("VALUE_GRADE_D", $this->object->ects_grades["D"]);
			$this->tpl->setVariable("ECTS_GRADE_E", "E - " . $this->lng->txt("ects_grade_e_short"));
			$this->tpl->setVariable("VALUE_GRADE_E", $this->object->ects_grades["E"]);
			
			$this->tpl->parseCurrentBlock();
		}

		$this->tpl->setCurrentBlock("adm_content");
		$this->tpl->setVariable("ACTION_MARKS", $this->ctrl->getFormAction($this));
		$this->tpl->setVariable("HEADER_SHORT", $this->lng->txt("tst_mark_short_form"));
		$this->tpl->setVariable("HEADER_OFFICIAL", $this->lng->txt("tst_mark_official_form"));
		$this->tpl->setVariable("HEADER_PERCENTAGE", $this->lng->txt("tst_mark_minimum_level"));
		$this->tpl->setVariable("HEADER_PASSED", $this->lng->txt("tst_mark_passed"));
		if ($ilAccess->checkAccess("write", "", $this->ref_id) && $this->object->canEditMarks()) 
		{
			$this->tpl->setVariable("BUTTON_NEW", $this->lng->txt("tst_mark_create_new_mark_step"));
			$this->tpl->setVariable("BUTTON_NEW_SIMPLE", $this->lng->txt("tst_mark_create_simple_mark_schema"));
			$this->tpl->setVariable("SAVE", $this->lng->txt("save"));
			$this->tpl->setVariable("CANCEL", $this->lng->txt("cancel"));
		}
		$this->tpl->parseCurrentBlock();
	}

	/**
	* Deletes all user data for the test object
	*
	* Deletes all user data for the test object
	*
	* @access	public
	*/
	function confirmDeleteAllUserResultsObject()
	{
		$this->object->removeAllTestEditings();

		// Update lp status
		include_once './Services/Tracking/classes/class.ilLPStatusWrapper.php';
		ilLPStatusWrapper::_refreshStatus($this->object->getId());

		ilUtil::sendSuccess($this->lng->txt("tst_all_user_data_deleted"), true);
		$this->ctrl->redirect($this, "participants");
	}
	
	/**
	* Deletes the selected user data for the test object
	*
	* Deletes the selected user data for the test object
	*
	* @access	public
	*/
	function confirmDeleteSelectedUserDataObject()
	{
		$active_ids = array();
		foreach ($_POST["chbUser"] as $active_id)
		{
			if ($this->object->getFixedParticipants())
			{
				array_push($active_ids, $this->object->getActiveIdOfUser($active_id));
			}
			else
			{
				array_push($active_ids, $active_id);
			}
		}
		$this->object->removeSelectedTestResults($active_ids);

		// Update lp status
		include_once './Services/Tracking/classes/class.ilLPStatusWrapper.php';
		ilLPStatusWrapper::_refreshStatus($this->object->getId());

		ilUtil::sendSuccess($this->lng->txt("tst_selected_user_data_deleted"), true);
		$this->ctrl->redirect($this, "participants");
	}
	
	/**
	* Cancels the deletion of all user data for the test object
	*
	* Cancels the deletion of all user data for the test object
	*
	* @access	public
	*/
	function cancelDeleteSelectedUserDataObject()
	{
		$this->ctrl->redirect($this, "participants");
	}
	
	/**
	* Asks for a confirmation to delete all user data of the test object
	*
	* Asks for a confirmation to delete all user data of the test object
	* 
	* DEPRECATED?
	*
	* @access	public
	*/
	function deleteAllUserDataObject()
	{
		ilUtil::sendQuestion($this->lng->txt("confirm_delete_all_user_data"));
		$this->tpl->addBlockFile("ADM_CONTENT", "adm_content", "tpl.il_as_tst_maintenance.html", "Modules/Test");

		$this->tpl->setCurrentBlock("confirm_delete");
		$this->tpl->setVariable("BTN_CONFIRM_DELETE_ALL", $this->lng->txt("confirm"));
		$this->tpl->setVariable("BTN_CANCEL_DELETE_ALL", $this->lng->txt("cancel"));
		$this->tpl->parseCurrentBlock();

		$this->tpl->setCurrentBlock("adm_content");
		$this->tpl->setVariable("FORM_ACTION", $this->ctrl->getFormAction($this));
		$this->tpl->parseCurrentBlock();
	}
	
	/**
	* Asks for a confirmation to delete all user data of the test object
	*/
	public function deleteAllUserResultsObject()
	{
		// display confirmation message
		include_once("./Services/Utilities/classes/class.ilConfirmationGUI.php");
		$cgui = new ilConfirmationGUI();
		$cgui->setFormAction($this->ctrl->getFormAction($this, "participants"));
		$cgui->setHeaderText($this->lng->txt("delete_all_user_data_confirmation"));
		$cgui->setCancel($this->lng->txt("cancel"), "participants");
		$cgui->setConfirm($this->lng->txt("proceed"), "confirmDeleteAllUserResults");
		
		$this->tpl->setContent($cgui->getHTML());
	}
	
	/**
	* Asks for a confirmation to delete selected user data of the test object
	*
	* Asks for a confirmation to delete selected user data of the test object
	*
	* @access	public
	*/
	function deleteSingleUserResultsObject()
	{
		if (count($_POST["chbUser"]) == 0)
		{
			ilUtil::sendInfo($this->lng->txt("select_one_user"), TRUE);
			$this->ctrl->redirect($this, "participants");
		}
		
		include_once("./Services/Utilities/classes/class.ilConfirmationGUI.php");
		$cgui = new ilConfirmationGUI();
		$cgui->setHeaderText($this->lng->txt("confirm_delete_single_user_data"));

		$cgui->setFormAction($this->ctrl->getFormAction($this));
		$cgui->setCancel($this->lng->txt("cancel"), "cancelDeleteSelectedUserData");
		$cgui->setConfirm($this->lng->txt("confirm"), "confirmDeleteSelectedUserData");
								
		include_once './Services/User/classes/class.ilObjUser.php';	
		foreach ($_POST["chbUser"] as $key => $active_id)
		{
			if ($this->object->getFixedParticipants())
			{
				$user_id = $active_id;
			}
			else
			{
				$user_id = $this->object->_getUserIdFromActiveId($active_id);
			}
			$user = ilObjUser::_lookupName($user_id);
		
			if ($this->object->getAnonymity())
			{
				$name = $this->lng->txt("anonymous");
			}
			else if($user["lastname"])
			{
				$name = $user["lastname"].", ".$user["firstname"]." (".
					$user["login"].")";
			}
			else
			{
				$name = $this->lng->txt("deleted_user");				
			}
		
			$cgui->addItem("chbUser[]", $active_id, $name,
				ilUtil::getImagePath("icon_usr.png"), $this->lng->txt("usr"));
		}
		
		$this->tpl->setContent($cgui->getHTML());
	}
	
	/**
	* Creates the change history for a test
	*
	* Creates the change history for a test
	*
	* @access	public
	*/
	function historyObject()
	{
		include_once "./Modules/Test/classes/tables/class.ilTestHistoryTableGUI.php";
		$table_gui = new ilTestHistoryTableGUI($this, 'history');
		$table_gui->setTestObject($this->object);
		include_once "./Modules/Test/classes/class.ilObjAssessmentFolder.php";
		$log =& ilObjAssessmentFolder::_getLog(0, time(), $this->object->getId(), TRUE);
		$table_gui->setData($log);
		$this->tpl->setVariable('ADM_CONTENT', $table_gui->getHTML());	
	}
	
	function initImportForm($a_new_type)
	{
		include_once("Services/Form/classes/class.ilPropertyFormGUI.php");
		$form = new ilPropertyFormGUI();
		$form->setTarget("_top");
		$new_type = $_POST["new_type"] ? $_POST["new_type"] : $_GET["new_type"];
		$this->ctrl->setParameter($this, "new_type", $new_type);
		$form->setFormAction($this->ctrl->getFormAction($this));
		$form->setTitle($this->lng->txt("import_tst"));

		// file
		include_once("./Services/Form/classes/class.ilFileInputGUI.php");
		$fi = new ilFileInputGUI($this->lng->txt("import_file"), "xmldoc");
		$fi->setSuffixes(array("zip"));
		$fi->setRequired(true);
		$form->addItem($fi);

		// question pool
		include_once("./Modules/Test/classes/class.ilObjTest.php");
		$tst = new ilObjTest();
		$questionpools = $tst->getAvailableQuestionpools(TRUE, FALSE, TRUE, TRUE);
		if (count($questionpools))
		{
			$options = array("-1" => $this->lng->txt("dont_use_questionpool"));
			foreach ($questionpools as $key => $value)
			{
				$options[$key] = $value["title"];
			}

			$pool = new ilSelectInputGUI($this->lng->txt("select_questionpool"), "qpl");
			$pool->setOptions($options);
			$form->addItem($pool);
		}

		$form->addCommandButton("importFile", $this->lng->txt("import"));
		$form->addCommandButton("cancel", $this->lng->txt("cancel"));

		return $form;
	}

 /**
	* Evaluates the actions on the participants page
	*
	* @access	public
	*/
	function participantsActionObject()
	{
		$command = $_POST["command"];
		if (strlen($command))
		{
			$method = $command . "Object";
			if (method_exists($this, $method))
			{
				$this->$method();
				return;
			}
		}
		$this->ctrl->redirect($this, "participants");
	}

 /**
	* Creates the output of the test participants
	*
	* @access	public
	*/
	function participantsObject()
	{
		global $ilAccess, $ilToolbar, $lng;
		
		$this->getParticipantsSubTabs();
		
		if (!$ilAccess->checkAccess("write", "", $this->ref_id)) 
		{
			// allow only write access
			ilUtil::sendInfo($this->lng->txt("cannot_edit_test"), true);
			$this->ctrl->redirect($this, "infoScreen");
		}
		
		if( $this->testQuestionSetConfigFactory->getQuestionSetConfig()->areDepenciesBroken($this->tree) )
		{
			ilUtil::sendFailure(
					$this->testQuestionSetConfigFactory->getQuestionSetConfig()->getDepenciesBrokenMessage($this->lng)
			);
		}
		elseif( $this->testQuestionSetConfigFactory->getQuestionSetConfig()->areDepenciesInVulnerableState($this->tree) )
		{
			ilUtil::sendInfo(
					$this->questionSetConfig->getDepenciesInVulnerableStateMessage($this->lng)
			);
		}

		if ($this->object->getFixedParticipants())
		{
			// search button
			include_once './Services/Search/classes/class.ilRepositorySearchGUI.php';
			ilRepositorySearchGUI::fillAutoCompleteToolbar(
				$this,
				$tb,
				array(
					'auto_complete_name'	=> $lng->txt('user'),
					'submit_name'			=> $lng->txt('add')
				)
			);

			// search button
			$ilToolbar->addButton($this->lng->txt("tst_search_users"),
				$this->ctrl->getLinkTargetByClass('ilRepositorySearchGUI','start'));


			$participants =& $this->object->getInvitedUsers();
			$rows = array();
			foreach ($participants as $data)
			{
				$maxpass = $this->object->_getMaxPass($data["active_id"]);
				if (!is_null($maxpass))
				{
					$maxpass += 1;
				}
				$access = "";
				if (strlen($data["active_id"]))
				{
					$last_access = $this->object->_getLastAccess($data["active_id"]);
					$access = $last_access;
				}
				$this->ctrl->setParameterByClass('iltestevaluationgui', 'active_id', $data['active_id']);
				
				if ($data['active_id'] == null) // if no active id is set, user is invitee not participant...
				{
					if ( strlen($data["firstname"].$data["lastname"]) == 0 )
					{
						$fullname = $lng->txt("deleted_user");
					}
					else if($this->object->getAnonymity())
					{
					 	$fullname = $lng->txt('anonymous');	
					}
					else
					{
						$fullname = trim($data["lastname"] . ", " . $data["firstname"] . " " . $data["title"]);
					}
				} else {
					include_once "./Modules/Test/classes/class.ilObjTestAccess.php";
					$fullname = ilObjTestAccess::_getParticipantData($data['active_id']);					
				}
				
				array_push($rows, array(
					'usr_id' => $data["usr_id"],
					'active_id' => $data['active_id'],
					'login' => $data["login"],
					'clientip' => $data["clientip"],
					'firstname' => $data["firstname"],
					'lastname' => $data["lastname"],
					'name' => $fullname,
					'started' => ($data["active_id"] > 0) ? 1 : 0,
					'finished' => ($data["test_finished"] == 1) ? 1 : 0,
					'access' => $access,
					'maxpass' => $maxpass,
					'result' => $this->ctrl->getLinkTargetByClass('iltestevaluationgui', 'outParticipantsResultsOverview')
				));
			}
			include_once "./Modules/Test/classes/tables/class.ilTestFixedParticipantsTableGUI.php";
			$table_gui = new ilTestFixedParticipantsTableGUI( $this, 'participants',
					$this->testQuestionSetConfigFactory->getQuestionSetConfig()->areDepenciesBroken(),
					$this->object->getAnonymity(), count($rows)
			);
			$table_gui->setFilterCommand('fpSetFilter');
			$table_gui->setResetCommand('fpResetFiler');
			$rows = $this->applyFilterCriteria($rows);
			$table_gui->setData($rows);
			$this->tpl->setVariable('ADM_CONTENT', $table_gui->getHTML());	
		}
		else
		{
			$participants =& $this->object->getTestParticipants();
			$rows = array();
			foreach ($participants as $data)
			{
				$maxpass = $this->object->_getMaxPass($data["active_id"]);
				if (!is_null($maxpass))
				{
					$maxpass += 1;
				}
				$access = "";
				if (strlen($data["active_id"]))
				{
					$last_access = $this->object->_getLastAccess($data["active_id"]);
					$access = $last_access;
				}
				$this->ctrl->setParameterByClass('iltestevaluationgui', 'active_id', $data['active_id']);

				include_once "./Modules/Test/classes/class.ilObjTestAccess.php";
				$fullname = ilObjTestAccess::_getParticipantData($data['active_id']);					
				array_push($rows, array(
					'usr_id' => $data["active_id"],
					'active_id' => $data['active_id'],
					'login' => $data["login"],
					'name' => $fullname,
					'firstname' => $data["firstname"],
					'lastname' => $data["lastname"],
					'started' => ($data["active_id"] > 0) ? 1 : 0,
					'finished' => ($data["test_finished"] == 1) ? 1 : 0,
					'access' => $access,
					'maxpass' => $maxpass,
					'result' => $this->ctrl->getLinkTargetByClass('iltestevaluationgui', 'outParticipantsResultsOverview')
				));
			}
			include_once "./Modules/Test/classes/tables/class.ilTestParticipantsTableGUI.php";
			$table_gui = new ilTestParticipantsTableGUI( $this, 'participants',
					$this->testQuestionSetConfigFactory->getQuestionSetConfig()->areDepenciesBroken(),
					$this->object->getAnonymity(), count($rows)
			);
			$table_gui->setFilterCommand('npSetFilter');
			$table_gui->setResetCommand('npResetFilter');
			$rows = $this->applyFilterCriteria($rows);
			$table_gui->setData($rows);
			$this->tpl->setVariable('ADM_CONTENT', $table_gui->getHTML());	
		}
	}
	
	public function timingOverviewObject()
	{
		$this->getParticipantsSubTabs();

		include_once "./Modules/Test/classes/tables/class.ilTimingOverviewTableGUI.php";
		$table_gui = new ilTimingOverviewTableGUI($this, 'timingOverview');
		
		$participants =& $this->object->getTestParticipants();#
		$times = $this->object->getStartingTimeOfParticipants();
		$addons = $this->object->getTimeExtensionsOfParticipants();

		$tbl_data = array();
		$i = 0;
		foreach ($participants as $participant)
		{
			$started = "";
			if ($times[$participant['active_id']])
			{
				$started = $this->lng->txt('tst_started').': '.ilDatePresentation::formatDate(new ilDateTime($times[$participant['active_id']], IL_CAL_DATETIME));
				$tbl_data[$i]['started'] = $started;
			}
			else
			{
				$tbl_data[$i]['started'] = '';
			}
			
			if ($addons[$participant['active_id']] > 0) 
			{
//				$started .= ", " . $this->lng->txt('extratime') . ': ' . $addons[$participant['active_id']] . ' ' . $this->lng->txt('minutes');
				$tbl_data[$i]['extratime'] = $addons[$participant['active_id']];
			}	
			
			$tbl_data[$i]['login'] = $participant['login'];

			if ($this->object->getAnonymity())
			{
				$name = $this->lng->txt("anonymous");
			}
			else
			{
				$name = $participant['lastname'] . ', ' . $participant['firstname'];
			}
			
			$tbl_data[$i]['name'] = $name;
			
//			$options[$participant['active_id']] = $participant['login'] . ' (' . $participant['lastname'] . ', ' . $participant['firstname'] . ')'.$started;
		}
		$table_gui->setData($tbl_data);
		
		$this->tpl->setContent($table_gui->getHTML());
	}
	
	public function timingObject()
	{
		$this->getParticipantsSubTabs();

		global $ilAccess;
		
		if (!$ilAccess->checkAccess("write", "", $this->ref_id))
		{
			// allow only write access
			ilUtil::sendInfo($this->lng->txt("cannot_edit_test"), true);
			$this->ctrl->redirect($this, "infoScreen");
		}

		if ($this->object->getProcessingTimeInSeconds() > 0 && $this->object->getNrOfTries() == 1)
		{
			$form = $this->formTimingObject();
			if (count($_POST) && $form->checkInput())
			{
				$res = $this->object->addExtraTime($form->getInput('participant'), $form->getInput('extratime'));
				ilUtil::sendSuccess(sprintf($this->lng->txt('tst_extratime_added'), $form->getInput('extratime')), true);
				$this->ctrl->redirect($this, 'timingOverview');
			}
			else
			{
				return $this->tpl->setVariable("ADM_CONTENT", $form->getHTML());
			}
		}
		else
		{
			ilUtil::sendInfo($this->lng->txt("tst_extratime_notavailable"));
		}
	}

	private function formTimingObject()
	{
		global $ilAccess;

		include_once("./Services/Form/classes/class.ilPropertyFormGUI.php");
		$form = new ilPropertyFormGUI();
		$form->setFormAction($this->ctrl->getFormAction($this));
		$form->setTableWidth("100%");
		$form->setId("tst_change_workingtime");
		$form->setTitle($this->lng->txt("tst_change_workingtime"));

		// test users
		$participantslist = new ilSelectInputGUI($this->lng->txt('participants'), "participant");
		$participants =& $this->object->getTestParticipants();
		$times = $this->object->getStartingTimeOfParticipants();
		$addons = $this->object->getTimeExtensionsOfParticipants();
		$options = array(
			'' => $this->lng->txt('please_select'),
			'0' => $this->lng->txt('all_participants')
		);
		foreach ($participants as $participant)
		{
			$started = "";

			if ($this->object->getAnonymity())
			{
				$name = $this->lng->txt("anonymous");
			}
			else
			{
				$name = $participant['lastname'] . ', ' . $participant['firstname']; 
			}
			
			
			if ($times[$participant['active_id']])
			{
				$started = ", ".$this->lng->txt('tst_started').': '.ilDatePresentation::formatDate(new ilDateTime($times[$participant['active_id']], IL_CAL_DATETIME));
			}
			if ($addons[$participant['active_id']] > 0) $started .= ", " . $this->lng->txt('extratime') . ': ' . $addons[$participant['active_id']] . ' ' . $this->lng->txt('minutes');
			$options[$participant['active_id']] = $participant['login'] . ' (' .$name. ')'.$started;
		}
		$participantslist->setRequired(true);
		$participantslist->setOptions($options);
		$form->addItem($participantslist);

		// extra time
		$extratime = new ilNumberInputGUI($this->lng->txt("extratime"), "extratime");
		$extratime->setRequired(true);
		$extratime->setMinValue(0);
		$extratime->setMinvalueShouldBeGreater(true);
		$extratime->setSuffix($this->lng->txt('minutes'));
		$extratime->setSize(5);
		$form->addItem($extratime);

		if (is_array($_POST) && strlen($_POST['cmd']['timing'])) $form->setValuesByArray($_POST);

		if ($ilAccess->checkAccess("write", "", $_GET["ref_id"])) $form->addCommandButton("timing", $this->lng->txt("save"));
		$form->addCommandButton('timingOverview', $this->lng->txt("cancel"));
		return $form;
	}
	
	public function showTimingFormObject()
	{
		$form = $this->formTimingObject();
		$this->tpl->setContent($form->getHTML());
	}	
	
	function applyFilterCriteria($in_rows)
	{
		global $ilDB;

		$sess_filter = $_SESSION['form_']['selection'];
		$sess_filter = str_replace('"','',$sess_filter);
		$sess_filter = explode(':', $sess_filter);
		$filter = substr($sess_filter[2],0, strlen($sess_filter[2])-1);
		
		if ($filter == 'all' || $filter == false)
		{
			return $in_rows; #unchanged - no filter.
		}
		
		$with_result = array();
		$without_result = array();
		foreach ($in_rows as $row)
		{
			$result = $ilDB->query(
				'SELECT count(solution_id) count
				FROM tst_solutions
				WHERE active_fi = ' . $ilDB->quote($row['active_id'])
			);
			$count = $ilDB->fetchAssoc($result);
			$count = $count['count'];
			
			if ($count == 0)
			{
				$without_result[] = $row;
			}
			else
			{
				$with_result[] = $row;
			}			
		}
		
		if ($filter == 'withSolutions')
		{
			return $with_result;
		}
		return $without_result;

	}
	
	function fpSetFilterObject()
	{
		include_once("./Modules/Test/classes/tables/class.ilTestFixedParticipantsTableGUI.php");
		$table_gui = new ilTestFixedParticipantsTableGUI($this, "participants", $this->object->getAnonymity(), count($rows));
		$table_gui->writeFilterToSession();        // writes filter to session
		$table_gui->resetOffset();                // sets record offest to 0 (first page)
		$this->participantsObject();
	}

	function fpResetFilterObject()
	{
		include_once("./Modules/Test/classes/tables/class.ilTestFixedParticipantsTableGUI.php");
		$table_gui = new ilTestFixedParticipantsTableGUI(
			$this, "participants", false, $this->object->getAnonymity(), count($rows)
		);
		$table_gui->resetFilter();        // writes filter to session
		$table_gui->resetOffset();                // sets record offest to 0 (first page)
		$this->participantsObject();
	}

	function npSetFilterObject()
	{
		include_once("./Modules/Test/classes/tables/class.ilTestParticipantsTableGUI.php");
		$table_gui = new ilTestParticipantsTableGUI(
			$this, "participants", false, $this->object->getAnonymity(), count($rows)
		);
		$table_gui->writeFilterToSession();        // writes filter to session
		$table_gui->resetOffset();                // sets record offest to 0 (first page)
		$this->participantsObject();
		
	}
	
	function npResetFilterObject()
	{
		include_once("./Modules/Test/classes/tables/class.ilTestParticipantsTableGUI.php");
		$table_gui = new ilTestParticipantsTableGUI(
			$this, "participants", false, $this->object->getAnonymity(), count($rows)
		);
		$table_gui->resetFilter();        // writes filter to session
		$table_gui->resetOffset();                // sets record offest to 0 (first page)
		$this->participantsObject();
		
	}
	
 /**
	* Shows the pass overview and the answers of one ore more users for the scored pass
	*
	* @access	public
	*/
	function showDetailedResultsObject()
	{
		if (count($_POST))
		{
			$_SESSION["show_user_results"] = $_POST["chbUser"];
		}
		$this->showUserResults($show_pass_details = TRUE, $show_answers = TRUE, $show_reached_points = TRUE);
	}

 /**
	* Shows the answers of one ore more users for the scored pass
	*
	* @access	public
	*/
	function showUserAnswersObject()
	{
		if (count($_POST))
		{
			$_SESSION["show_user_results"] = $_POST["chbUser"];
		}
		$this->showUserResults($show_pass_details = FALSE, $show_answers = TRUE);
	}

 /**
	* Shows the pass overview of the scored pass for one ore more users
	*
	* @access	public
	*/
	function showPassOverviewObject()
	{
		if (count($_POST))
		{
			$_SESSION["show_user_results"] = $_POST["chbUser"];
		}
		$this->showUserResults($show_pass_details = TRUE, $show_answers = FALSE);
	}
	
 /**
	* Shows the pass overview of the scored pass for one ore more users
	*
	* @access	public
	*/
	function showUserResults($show_pass_details, $show_answers, $show_reached_points = FALSE)
	{
		$show_user_results = $_SESSION["show_user_results"];
		
		if (count($show_user_results) == 0)
		{
			ilUtil::sendInfo($this->lng->txt("select_one_user"), TRUE);
			$this->ctrl->redirect($this, "participants");
		}


		$template = $this->createUserResults( $show_pass_details, $show_answers, $show_reached_points, $show_user_results);

		$this->tpl->setVariable("ADM_CONTENT", $template->get());
		$this->tpl->addCss(ilUtil::getStyleSheetLocation("output", "test_print.css", "Modules/Test"), "print");
		if ($this->object->getShowSolutionAnswersOnly())
		{
			$this->tpl->addCss(ilUtil::getStyleSheetLocation("output", "test_print_hide_content.css", "Modules/Test"), "print");
		}
	}

	function removeParticipantObject()
	{
		if (is_array($_POST["chbUser"])) 
		{
			foreach ($_POST["chbUser"] as $user_id)
			{
				$this->object->disinviteUser($user_id);
			}
		}
		else
		{
			ilUtil::sendInfo($this->lng->txt("select_one_user"), true);
		}
		$this->ctrl->redirect($this, "participants");
	}
	
	function saveClientIPObject()
	{
		if (is_array($_POST["chbUser"])) 
		{
			foreach ($_POST["chbUser"] as $user_id)
			{
				$this->object->setClientIP($user_id, $_POST["clientip_".$user_id]);
			}
		}
		else
		{
			ilUtil::sendInfo($this->lng->txt("select_one_user"), true);
		}
		$this->ctrl->redirect($this, "participants");
	}
	
	/**
	* Print tab to create a print of all questions with points and solutions
	*
	* Print tab to create a print of all questions with points and solutions
	*
	* @access	public
	*/
	function printobject() 
	{
		global $ilAccess, $ilias;
		if (!$ilAccess->checkAccess("write", "", $this->ref_id)) 
		{
			// allow only write access
			ilUtil::sendInfo($this->lng->txt("cannot_edit_test"), true);
			$this->ctrl->redirect($this, "infoScreen");
		}
		$this->getQuestionsSubTabs();
		$template = new ilTemplate("tpl.il_as_tst_print_test_confirm.html", TRUE, TRUE, "Modules/Test");

		$this->ctrl->setParameter($this, "pdf", "1");
		$template->setCurrentBlock("pdf_export");
		$template->setVariable("PDF_URL", $this->ctrl->getLinkTarget($this, "print"));
		$this->ctrl->setParameter($this, "pdf", "");
		$template->setVariable("PDF_TEXT", $this->lng->txt("pdf_export"));
		$template->setVariable("PDF_IMG_ALT", $this->lng->txt("pdf_export"));
		$template->setVariable("PDF_IMG_URL", ilUtil::getHtmlPath(ilUtil::getImagePath("application-pdf.png")));
		$template->parseCurrentBlock();

		$this->tpl->addCss(ilUtil::getStyleSheetLocation("output", "test_print.css", "Modules/Test"), "print");
		
		global $ilUser;		
		$print_date = mktime(date("H"), date("i"), date("s"), date("m")  , date("d"), date("Y"));
		$max_points= 0;
		$counter = 1;
					
		foreach ($this->object->questions as $question) 
		{		
			$template->setCurrentBlock("question");
			$question_gui = $this->object->createQuestionGUI("", $question);
			$template->setVariable("COUNTER_QUESTION", $counter.".");
			$template->setVariable("TXT_QUESTION_ID", $this->lng->txt('question_id_short'));
			$template->setVariable("QUESTION_ID", $question_gui->object->getId());
			$template->setVariable("QUESTION_TITLE", ilUtil::prepareFormOutput($question_gui->object->getTitle()));
			if ($question_gui->object->getMaximumPoints() == 1)
			{
				$template->setVariable("QUESTION_POINTS", $question_gui->object->getMaximumPoints() . " " . $this->lng->txt("point"));
			}
			else
			{
				$template->setVariable("QUESTION_POINTS", $question_gui->object->getMaximumPoints() . " " . $this->lng->txt("points"));
			}
			$result_output = $question_gui->getSolutionOutput("", NULL, FALSE, TRUE, FALSE, $this->object->getShowSolutionFeedback());
			if (strlen($result_output) == 0) $result_output = $question_gui->getPreview(FALSE);
			$template->setVariable("SOLUTION_OUTPUT", $result_output);
			$template->parseCurrentBlock("question");
			$counter ++;
			$max_points += $question_gui->object->getMaximumPoints();
		}

		$template->setCurrentBlock("navigation_buttons");
		$template->setVariable("BUTTON_PRINT", $this->lng->txt("print"));
		$template->parseCurrentBlock();
		
		$template->setVariable("TITLE", ilUtil::prepareFormOutput($this->object->getTitle()));
		$template->setVariable("PRINT_TEST", ilUtil::prepareFormOutput($this->lng->txt("tst_print")));
		$template->setVariable("TXT_PRINT_DATE", ilUtil::prepareFormOutput($this->lng->txt("date")));
		$template->setVariable("VALUE_PRINT_DATE", ilUtil::prepareFormOutput(strftime("%c",$print_date)));
		$template->setVariable("TXT_MAXIMUM_POINTS", ilUtil::prepareFormOutput($this->lng->txt("tst_maximum_points")));
		$template->setVariable("VALUE_MAXIMUM_POINTS", ilUtil::prepareFormOutput($max_points));
		
		if (array_key_exists("pdf", $_GET) && ($_GET["pdf"] == 1))
		{
			//$this->object->deliverPDFfromHTML($template->get(), $this->object->getTitle());
			require_once 'class.ilTestPDFGenerator.php';
			ilTestPDFGenerator::generatePDF($template->get(), ilTestPDFGenerator::PDF_OUTPUT_DOWNLOAD, $this->object->getTitle());
		}
		else
		{
			$this->tpl->setVariable("PRINT_CONTENT", $template->get());
		}
	}

	/**
	 * Review tab to create a print of all questions without points and solutions
	 *
	 * Review tab to create a print of all questions without points and solutions
	 *
	 * @access	public
	 */
	function reviewobject()
	{
		global $ilAccess, $ilias;
		if (!$ilAccess->checkAccess("write", "", $this->ref_id))
		{
			// allow only write access
			ilUtil::sendInfo($this->lng->txt("cannot_edit_test"), true);
			$this->ctrl->redirect($this, "infoScreen");
		}
		$this->getQuestionsSubTabs();
		$template = new ilTemplate("tpl.il_as_tst_print_test_confirm.html", TRUE, TRUE, "Modules/Test");

		$this->ctrl->setParameter($this, "pdf", "1");
		$template->setCurrentBlock("pdf_export");
		$template->setVariable("PDF_URL", $this->ctrl->getLinkTarget($this, "review"));
		$this->ctrl->setParameter($this, "pdf", "");
		$template->setVariable("PDF_TEXT", $this->lng->txt("pdf_export"));
		$template->setVariable("PDF_IMG_ALT", $this->lng->txt("pdf_export"));
		$template->setVariable("PDF_IMG_URL", ilUtil::getHtmlPath(ilUtil::getImagePath("application-pdf.png")));
		$template->parseCurrentBlock();

		$this->tpl->addCss(ilUtil::getStyleSheetLocation("output", "test_print.css", "Modules/Test"), "print");

		global $ilUser;
		$print_date = mktime(date("H"), date("i"), date("s"), date("m")  , date("d"), date("Y"));
		$max_points= 0;
		$counter = 1;

		foreach ($this->object->questions as $question)
		{
			$template->setCurrentBlock("question");
			$question_gui = $this->object->createQuestionGUI("", $question);
			$template->setVariable("COUNTER_QUESTION", $counter.".");
			$template->setVariable("QUESTION_TITLE", ilUtil::prepareFormOutput($question_gui->object->getTitle()));
			if ($question_gui->object->getMaximumPoints() == 1)
			{
				$template->setVariable("QUESTION_POINTS", $question_gui->object->getMaximumPoints() . " " . $this->lng->txt("point"));
			}
			else
			{
				$template->setVariable("QUESTION_POINTS", $question_gui->object->getMaximumPoints() . " " . $this->lng->txt("points"));
			}
			/** @var $question_gui assQuestionGUI  */
			//$result_output = $question_gui->getTestOutput('', NULL, FALSE, FALSE, FALSE);
			$result_output = $question_gui->getPreview(false);

			if (strlen($result_output) == 0) $result_output = $question_gui->getPreview(FALSE);
			$template->setVariable("SOLUTION_OUTPUT", $result_output);
			$template->parseCurrentBlock("question");
			$counter ++;
			$max_points += $question_gui->object->getMaximumPoints();
		}



		$template->setVariable("TITLE", ilUtil::prepareFormOutput($this->object->getTitle()));
		$template->setVariable("PRINT_TEST", ilUtil::prepareFormOutput($this->lng->txt("review_view")));
		$template->setVariable("TXT_PRINT_DATE", ilUtil::prepareFormOutput($this->lng->txt("date")));
		$template->setVariable("VALUE_PRINT_DATE", ilUtil::prepareFormOutput(strftime("%c",$print_date)));
		$template->setVariable("TXT_MAXIMUM_POINTS", ilUtil::prepareFormOutput($this->lng->txt("tst_maximum_points")));
		$template->setVariable("VALUE_MAXIMUM_POINTS", ilUtil::prepareFormOutput($max_points));

		if (array_key_exists("pdf", $_GET) && ($_GET["pdf"] == 1))
		{
			//$this->object->deliverPDFfromHTML($template->get(), $this->object->getTitle());
			require_once 'class.ilTestPDFGenerator.php';
			$content = $template->get();
			ilTestPDFGenerator::generatePDF($template->get(), ilTestPDFGenerator::PDF_OUTPUT_DOWNLOAD, $this->object->getTitle());
		}
		else
		{
			$template->setCurrentBlock("navigation_buttons");
			$template->setVariable("BUTTON_PRINT", $this->lng->txt("print"));
			$template->parseCurrentBlock();
			
			
			$this->tpl->setVariable("PRINT_CONTENT", $template->get());
		}
	}	
	
	function addParticipantsObject($a_user_ids = array())
	{
		$countusers = 0;
		// add users 
		if (is_array($a_user_ids))
		{
			$i = 0;
			foreach ($a_user_ids as $user_id)
			{
				$client_ip = $_POST["client_ip"][$i];
				$this->object->inviteUser($user_id, $client_ip);
				$countusers++;
				$i++;
			}
		}
		$message = "";
		if ($countusers)
		{
			$message = $this->lng->txt("tst_invited_selected_users");
		}
		if (strlen($message))
		{
			ilUtil::sendInfo($message, TRUE);
		}
		else
		{
			ilUtil::sendInfo($this->lng->txt("tst_invited_nobody"), TRUE);
			return false;
		}
		
		$this->ctrl->redirect($this, "participants");
	}

	/**
	 * Displays the settings page for test defaults
	 */
	public function defaultsObject()
	{
		/**
		 * @var $ilAccess  ilAccessHandler
		 * @var $ilToolbar ilToolbarGUI
		 * @var $tpl       ilTemplage
		 */
		global $ilAccess, $ilToolbar, $tpl;

		if(!$ilAccess->checkAccess("write", "", $this->ref_id))
		{
			ilUtil::sendInfo($this->lng->txt("cannot_edit_test"), true);
			$this->ctrl->redirect($this, "infoScreen");
		}

		$ilToolbar->setFormAction($this->ctrl->getFormAction($this, 'addDefaults'));
		$ilToolbar->addFormButton($this->lng->txt('add'), 'addDefaults');
		require_once 'Services/Form/classes/class.ilTextInputGUI.php';
		$ilToolbar->addInputItem(new ilTextInputGUI($this->lng->txt('tst_defaults_defaults_of_test'), 'name'), true);

		require_once 'Modules/Test/classes/tables/class.ilTestPersonalDefaultSettingsTableGUI.php';
		$table    = new ilTestPersonalDefaultSettingsTableGUI($this, 'defaults');
		$defaults = $this->object->getAvailableDefaults();
		$table->setData((array)$defaults);
		$tpl->setContent($table->getHTML());
	}
	
	/**
	 * Deletes selected test defaults
	 */
	public function deleteDefaultsObject()
	{
		if(isset($_POST['chb_defaults']) && is_array($_POST['chb_defaults']) && count($_POST['chb_defaults']))
		{
			foreach($_POST['chb_defaults'] as $test_default_id)
			{
				$this->object->deleteDefaults($test_default_id);
			}
		}
		else
		{
			ilUtil::sendInfo($this->lng->txt('select_one'));
		}
		$this->defaultsObject();
	}

	/**
	 * 
	 */
	public function confirmedApplyDefaultsObject()
	{
		$this->applyDefaultsObject(true);
		return;
	}

	/**
	 * Applies the selected test defaults
	 */
	public function applyDefaultsObject($confirmed = false)
	{
		if( count($_POST["chb_defaults"]) != 1 )
		{
			ilUtil::sendInfo(
				$this->lng->txt("tst_defaults_apply_select_one")
			);
			
			return $this->defaultsObject();
		}
			
		// do not apply if user datasets exist
		if($this->object->evalTotalPersons() > 0)
		{
			ilUtil::sendInfo(
				$this->lng->txt("tst_defaults_apply_not_possible")
			);

			return $this->defaultsObject();
		}

		$defaults =& $this->object->getTestDefaults($_POST["chb_defaults"][0]);
		$defaultSettings = unserialize($defaults["defaults"]);

		$randomTestSettingSwitched = $this->isRandomTestSettingSwitched(
			$defaultSettings["isRandomTest"]
		);

		switch( true )
		{
			case !$randomTestSettingSwitched:
			case $this->object->getQuestionCount() == 0:
			case $confirmed:

				break;
			
			default:

				if( !$defaultSettings["isRandomTest"] ) //&& $this->object->areRandomTestQuestionpoolsConfigured() )
				{
					// user tries to change from a random test with existing random question pools to a non random test
					$this->confirmChangeProperties(self::SWITCH_RANDOM_TEST_SETTING_TO_DISABLED, 'confirmedApplyDefaults', 'defaults');
					return;
				}
				elseif( $defaultSettings["isRandomTest"] ) //&& $this->object->doesNonRandomTestQuestionsExist() )
				{
					// user tries to change from a non random test with existing questions to a random test
					$this->confirmChangeProperties(self::SWITCH_RANDOM_TEST_SETTING_TO_ENABLED, 'confirmedApplyDefaults', 'defaults');
					return;
				}
		}

		if( $randomTestSettingSwitched && $this->object->isOnline() )
		{
			$this->object->setOnline(false);

			$info = $this->lng->txt(
				"tst_set_offline_due_to_switched_random_test_setting"
			);

			ilUtil::sendInfo($info, true);
		}

		$this->object->applyDefaults($defaults);

		ilUtil::sendSuccess($this->lng->txt("tst_defaults_applied"), true);

		if($randomTestSettingSwitched)
		{
			if($this->object->isRandomTest())
			{
				$this->object->removeNonRandomTestData();
			}
			else
			{
				$this->object->removeRandomTestData();
			}
		}

		$this->ctrl->redirect($this, 'defaults');
	}
	
	/**
	* Adds the defaults of this test to the defaults
	*/
	function addDefaultsObject()
	{
		if (strlen($_POST["name"]) > 0)
		{
			$this->object->addDefaults($_POST['name']);
		}
		else
		{
			ilUtil::sendInfo($this->lng->txt("tst_defaults_enter_name"));
		}
		$this->defaultsObject();
	}
	
	/**
	* this one is called from the info button in the repository
	* not very nice to set cmdClass/Cmd manually, if everything
	* works through ilCtrl in the future this may be changed
	*/
	function infoScreenObject()
	{
		$this->ctrl->setCmd("showSummary");
		$this->ctrl->setCmdClass("ilinfoscreengui");
		$this->infoScreen();
	}
	
	function redirectToInfoScreenObject()
	{
		$this->ctrl->setCmd("showSummary");
		$this->ctrl->setCmdClass("ilinfoscreengui");
		$this->infoScreen($_SESSION["lock"]);
	}
	
	/**
	* show information screen
	*/
	function infoScreen($session_lock = "")
	{
		global $ilAccess, $ilUser, $ilToolbar;

		$testSession = $this->testSessionFactory->getSession();
		$testSequence = $this->testSequenceFactory->getSequence($testSession);
		
		$testPlayerGUI = $this->testPlayerFactory->getPlayerGUI();
		
		$testSequence->handleQuestionVisibility($this->object, $_GET['crs_show_result']);
		
		if ($_GET['createRandomSolutions'])
		{
			$this->object->createRandomSolutions($_GET['createRandomSolutions']);
		}

		if (!$ilAccess->checkAccess("visible", "", $this->ref_id))
		{
			$this->ilias->raiseError($this->lng->txt("msg_no_perm_read"),$this->ilias->error_obj->MESSAGE);
		}

		include_once("./Services/InfoScreen/classes/class.ilInfoScreenGUI.php");
		$info = new ilInfoScreenGUI($this);

		$this->ctrl->setParameter($testPlayerGUI, "sequence", $testSession->getLastSequence());
		
		$info->setFormAction($this->ctrl->getFormAction($testPlayerGUI));
		
		if (strlen($session_lock))
		{
			$info->addHiddenElement("lock", $session_lock);
		}
		else
		{
			$info->addHiddenElement("lock", md5($_COOKIE['PHPSESSID'] . time()));
		}
		$online_access = false;
		if ($this->object->getFixedParticipants())
		{
			include_once "./Modules/Test/classes/class.ilObjTestAccess.php";
			$online_access_result = ilObjTestAccess::_lookupOnlineTestAccess($this->object->getId(), $ilUser->getId());
			if ($online_access_result === true)
			{
				$online_access = true;
			}
			else
			{
				ilUtil::sendInfo($online_access_result);
			}
		}
		if( $this->object->isOnline() && $this->object->isComplete( $this->testQuestionSetConfigFactory->getQuestionSetConfig() ) )
		{
			if ((!$this->object->getFixedParticipants() || $online_access) && $ilAccess->checkAccess("read", "", $this->ref_id))
			{
				$executable = $this->object->isExecutable($testSession, $ilUser->getId(), $allowPassIncrease = TRUE);
				if ($executable["executable"])
				{
					if( $this->object->areObligationsEnabled() && $this->object->hasObligations($this->object->getTestId()) )
					{
						ilUtil::sendInfo($GLOBALS['lng']->txt('tst_test_contains_obligatory_questions'));
					}
					
					if ($testSession->getActiveId() > 0)
					{
						// resume test
						
						if ($testSequence->hasStarted($testSession))
						{
							$resumeTestLabel = $this->lng->txt("tst_resume_test");
						}
						else
						{
							$resumeTestLabel = $this->object->getStartTestLabel($testSession->getActiveId());
						}
						
						$big_button[] = array('resumePlayer', $resumeTestLabel, true);
					}
					else
					{
						// start new test

						$big_button[] = array("startPlayer", $this->object->getStartTestLabel($testSession->getActiveId()), true);
					}
				}
				else
				{
					ilUtil::sendInfo($executable["errormessage"]);
				}
				if ($testSession->getActiveId() > 0)
				{
					// test results button
					if ($this->object->canShowTestResults($testSession, $ilUser->getId())) 
					{
						//$info->addFormButton("outUserResultsOverview", $this->lng->txt("tst_show_results"));
						$big_button[] = array("outUserResultsOverview", $this->lng->txt("tst_show_results"), false);
						if ($this->object->getHighscoreEnabled())
						{
							// Can also compare results then
							$big_button[] = array("outResultsToplist", $this->lng->txt("tst_show_toplist"), false);
						}
					}
				}
			}
			if ($testSession->getActiveId() > 0)
			{
				if ($this->object->canShowSolutionPrintview($ilUser->getId()))
				{
					//$info->addFormButton("outUserListOfAnswerPasses", $this->lng->txt("tst_list_of_answers_show"));
					$big_button[] = array("outUserListOfAnswerPasses", $this->lng->txt("tst_list_of_answers_show"), false);
				}
			}
		}

		if( !$this->object->isOnline() )
 		{
			$message = $this->lng->txt("test_is_offline");

			if($ilAccess->checkAccess("write", "", $this->ref_id))
			{
				$message .= "<br /><a href=\"".$this->ctrl->getLinkTargetByClass('ilobjtestsettingsgeneralgui')."\">".
					$this->lng->txt("test_edit_settings")."</a>";
			}

			ilUtil::sendInfo($message);
		}
		
		if( $ilAccess->checkAccess("write", "", $this->ref_id) )
		{
			$testQuestionSetConfig = $this->testQuestionSetConfigFactory->getQuestionSetConfig();
			
			if( $testQuestionSetConfig->areDepenciesBroken() )
			{
				ilUtil::sendFailure( $testQuestionSetConfig->getDepenciesBrokenMessage($this->lng) );
				
				$big_button = array();
			}
			elseif( $testQuestionSetConfig->areDepenciesInVulnerableState() )
			{
				ilUtil::sendInfo( $testQuestionSetConfig->getDepenciesInVulnerableStateMessage($this->lng) );
			}
		}
		
		if ($this->object->getShowInfo())
		{
			$info->enablePrivateNotes();
		}

		if($big_button)
		{
			$ilToolbar->setFormAction($this->ctrl->getFormAction($testPlayerGUI));
			foreach($big_button as $button)
			{
				$ilToolbar->addFormButton($button[1], $button[0], "", $button[2]);
			}
			$ilToolbar->setCloseFormTag(false);
			$info->setOpenFormTag(false);
		}
		
		if (strlen($this->object->getIntroduction()))
		{
			$info->addSection($this->lng->txt("tst_introduction"));
			$info->addProperty("", $this->object->prepareTextareaOutput($this->object->getIntroduction()).
					$info->getHiddenToggleButton());
		}
		else
		{
			$info->addSection("");
			$info->addProperty("", $info->getHiddenToggleButton());
		}

		$info->addSection($this->lng->txt("tst_general_properties"));
		if ($this->object->getShowInfo())
		{
			$info->addProperty($this->lng->txt("author"), $this->object->getAuthor());
			$info->addProperty($this->lng->txt("title"), $this->object->getTitle());
		}
		if( $this->object->isOnline() && $this->object->isComplete( $this->testQuestionSetConfigFactory->getQuestionSetConfig() ) )
		{
			if ((!$this->object->getFixedParticipants() || $online_access) && $ilAccess->checkAccess("read", "", $this->ref_id))
			{
				if ($this->object->getShowInfo() || !$this->object->getForceJS())
				{
					// use javascript
					$checked_javascript = false;
					if ($this->object->getJavaScriptOutput())
					{
						$checked_javascript = true;
					}
					if ($this->object->getForceJS())
					{
						$info->addProperty($this->lng->txt("tst_test_output"), $this->lng->txt("tst_use_javascript"));
					}
					else
					{
						$info->addPropertyCheckbox($this->lng->txt("tst_test_output"), "chb_javascript", 1, $this->lng->txt("tst_use_javascript"), $checked_javascript);
					}
				}
				// hide previous results
				if( !$this->object->isRandomTest() )
				{
					if ($this->object->getNrOfTries() != 1)
					{
						if ($this->object->getUsePreviousAnswers() == 0)
						{
							if ($this->object->getShowInfo())
							{
								$info->addProperty($this->lng->txt("tst_use_previous_answers"), $this->lng->txt("tst_dont_use_previous_answers"));
							}
						}
						else
						{
							$use_previous_answers = FALSE;
							if ($ilUser->prefs["tst_use_previous_answers"])
							{
								$checked_previous_answers = TRUE;
							}
							$info->addPropertyCheckbox($this->lng->txt("tst_use_previous_answers"), "chb_use_previous_answers", 1, $this->lng->txt("tst_use_previous_answers_user"), $checked_previous_answers);
						}
					}
				}
				if ($_SESSION["AccountId"] == ANONYMOUS_USER_ID)
				{
					$info->addPropertyTextinput($this->lng->txt("enter_anonymous_code"), "anonymous_id", "", 8, "setAnonymousId", $this->lng->txt("submit"));
				}
			}
		}

		$info->hideFurtherSections(false);
		
		if ($this->object->getShowInfo())
		{
			$info->addSection($this->lng->txt("tst_sequence_properties"));
			$info->addProperty($this->lng->txt("tst_sequence"), $this->lng->txt(($this->object->getSequenceSettings() == TEST_FIXED_SEQUENCE)? "tst_sequence_fixed":"tst_sequence_postpone"));
		
			$info->addSection($this->lng->txt("tst_heading_scoring"));
			$info->addProperty($this->lng->txt("tst_text_count_system"), $this->lng->txt(($this->object->getCountSystem() == COUNT_PARTIAL_SOLUTIONS)? "tst_count_partial_solutions":"tst_count_correct_solutions"));
			$info->addProperty($this->lng->txt("tst_score_mcmr_questions"), $this->lng->txt(($this->object->getMCScoring() == SCORE_ZERO_POINTS_WHEN_UNANSWERED)? "tst_score_mcmr_zero_points_when_unanswered":"tst_score_mcmr_use_scoring_system"));
			if ($this->object->isRandomTest())
			{
				$info->addProperty($this->lng->txt("tst_pass_scoring"), $this->lng->txt(($this->object->getPassScoring() == SCORE_BEST_PASS)? "tst_pass_best_pass":"tst_pass_last_pass"));
			}

			$info->addSection($this->lng->txt("tst_score_reporting"));
			$score_reporting_text = "";
			switch ($this->object->getScoreReporting())
			{
				case REPORT_AFTER_TEST:
					$score_reporting_text = $this->lng->txt("tst_report_after_test");
					break;
				case REPORT_ALWAYS:
					$score_reporting_text = $this->lng->txt("tst_report_after_first_question");
					break;
				case REPORT_AFTER_DATE:
					$score_reporting_text = $this->lng->txt("tst_report_after_date");
					break;
				case 4:
					$score_reporting_text = $this->lng->txt("tst_report_never");
					break;
			}
			$info->addProperty($this->lng->txt("tst_score_reporting"), $score_reporting_text); 
			$reporting_date = $this->object->getReportingDate();
			if ($reporting_date)
			{
				#preg_match("/(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})/", $reporting_date, $matches);
				#$txt_reporting_date = date($this->lng->text["lang_dateformat"] . " " . $this->lng->text["lang_timeformat"], mktime($matches[4], $matches[5], $matches[6], $matches[2], $matches[3], $matches[1]));
				#$info->addProperty($this->lng->txt("tst_score_reporting_date"), $txt_reporting_date);
				$info->addProperty($this->lng->txt('tst_score_reporting_date'),
					ilDatePresentation::formatDate(new ilDateTime($reporting_date,IL_CAL_TIMESTAMP)));
			}
	
			$info->addSection($this->lng->txt("tst_session_settings"));
			$info->addProperty($this->lng->txt("tst_nr_of_tries"), ($this->object->getNrOfTries() == 0)?$this->lng->txt("unlimited"):$this->object->getNrOfTries());
			if ($this->object->getNrOfTries() != 1)
			{
				$info->addProperty($this->lng->txt("tst_nr_of_tries_of_user"), ($testSession->getPass() == false)?$this->lng->txt("tst_no_tries"):$testSession->getPass());
			}

			if ($this->object->getEnableProcessingTime())
			{
				$info->addProperty($this->lng->txt("tst_processing_time"), $this->object->getProcessingTime());
			}
			if (strlen($this->object->getAllowedUsers()) && ($this->object->getAllowedUsersTimeGap()))
			{
				$info->addProperty($this->lng->txt("tst_allowed_users"), $this->object->getAllowedUsers());
			}
		
			$starting_time = $this->object->getStartingTime();
			if ($starting_time)
			{
				$info->addProperty($this->lng->txt("tst_starting_time"),
					ilDatePresentation::formatDate(new ilDateTime($starting_time,IL_CAL_TIMESTAMP)));
			}
			$ending_time = $this->object->getEndingTime();
			if ($ending_time)
			{
				$info->addProperty($this->lng->txt("tst_ending_time"),
					ilDatePresentation::formatDate(new ilDateTime($ending_time,IL_CAL_TIMESTAMP)));
			}
			$info->addMetaDataSections($this->object->getId(),0, $this->object->getType());
			// forward the command

			if($_GET['crs_show_result'] and !$testSequence->getFirstSequence())
			{
				#ilUtil::sendInfo($this->lng->txt('crs_all_questions_answered_successfully'));
			}			
		}
		
		$this->ctrl->forwardCommand($info);
	}

	function addLocatorItems()
	{
		global $ilLocator;
		switch ($this->ctrl->getCmd())
		{
			case "run":
			case "infoScreen":
			case "redirectToInfoScreen":
			case "start":
			case "resume":
			case "previous":
			case "next":
			case "summary":
			case "finishTest":
			case "outCorrectSolution":
			case "passDetails":
			case "showAnswersOfUser":
			case "outUserResultsOverview":
			case "backFromSummary":
			case "show_answers":
			case "setsolved":
			case "resetsolved":
			case "outTestSummary":
			case "outQuestionSummary":
			case "gotoQuestion":
			case "selectImagemapRegion":
			case "confirmSubmitAnswers":
			case "finalSubmission":
			case "postpone":
			case "redirectQuestion":
			case "outUserPassDetails":
			case "checkPassword":
				$ilLocator->addItem($this->object->getTitle(), $this->ctrl->getLinkTarget($this, "infoScreen"), "", $_GET["ref_id"]);
				break;
			case "eval_stat":
			case "evalAllUsers":
			case "evalUserDetail":
				$ilLocator->addItem($this->object->getTitle(), $this->ctrl->getLinkTarget($this, "eval_stat"), "", $_GET["ref_id"]);
				break;
			case "create":
			case "save":
			case "cancel":
			case "importFile":
			case "cloneAll":
			case "importVerifiedFile":
			case "cancelImport":
				break;
		default:
				$ilLocator->addItem($this->object->getTitle(), $this->ctrl->getLinkTarget($this, ""), "", $_GET["ref_id"]);
				break;
		}
	}
	
	function getBrowseForQuestionsTab(&$tabs_gui)
	{
		global $ilAccess;
		if ($ilAccess->checkAccess("write", "", $this->ref_id))
		{
			$this->ctrl->saveParameterByClass($this->ctrl->getCmdClass(), 'q_id');
			// edit page
			$tabs_gui->setBackTarget($this->lng->txt("backtocallingtest"), $this->ctrl->getLinkTargetByClass($this->ctrl->getCmdClass(), "questions"));
			$tabs_gui->addTarget("tst_browse_for_questions",
				$this->ctrl->getLinkTarget($this, "browseForQuestions"),
				array("browseForQuestions", "filter", "resetFilter", "resetTextFilter", "insertQuestions"),
				"", "", TRUE
			);
		}
	}
	
	function getRandomQuestionsTab(&$tabs_gui)
	{
		global $ilAccess;
		if ($ilAccess->checkAccess("write", "", $this->ref_id))
		{
			// edit page
			$tabs_gui->setBackTarget($this->lng->txt("backtocallingtest"), $this->ctrl->getLinkTarget($this, "questions"));
			$tabs_gui->addTarget("random_selection",
				$this->ctrl->getLinkTarget($this, "randomQuestions"),
				array("randomQuestions"),
				"", ""
			);
		}
	}

	function statisticsObject()
	{
	}

	/**
	* Shows the certificate editor
	*/
	function certificateObject()
	{
		include_once "./Services/Certificate/classes/class.ilCertificateGUI.php";
		include_once "./Modules/Test/classes/class.ilTestCertificateAdapter.php";
		$output_gui = new ilCertificateGUI(new ilTestCertificateAdapter($this->object));
		$output_gui->certificateEditor();
	}

	function getQuestionsSubTabs()
	{
		global $ilTabs, $ilCtrl;
		$ilTabs->activateTab('assQuestions');
		$a_cmd = $ilCtrl->getCmd();

		if (!$this->object->isRandomTest())
		{
                #if (in_array($this->object->getEnabledViewMode(), array('both', 'express'))) {
                    $questions_per_page = ($a_cmd == 'questions_per_page' || ($a_cmd == 'removeQuestions' && $_REQUEST['test_express_mode'])) ? true : false;

                    $this->tabs_gui->addSubTabTarget(
                            "questions_per_page_view",
                            $this->ctrl->getLinkTargetByClass('iltestexpresspageobjectgui', 'showPage'),
                            "", "", "", $questions_per_page);
                #}
		}
		include_once "Services/Administration/classes/class.ilSettingsTemplate.php";
		$template = new ilSettingsTemplate($this->object->getTemplate(), ilObjAssessmentFolderGUI::getSettingsTemplateConfig());

                if (!in_array('questions', $template->getHiddenTabs())) {
                    // questions subtab
                    $ilTabs->addSubTabTarget("edit_test_questions",
                             $this->ctrl->getLinkTarget($this,'questions'),
                             array("questions", "browseForQuestions", "questionBrowser", "createQuestion",
                             "randomselect", "filter", "resetFilter", "insertQuestions",
                             "back", "createRandomSelection", "cancelRandomSelect",
                             "insertRandomSelection", "removeQuestions", "moveQuestions",
                             "insertQuestionsBefore", "insertQuestionsAfter", "confirmRemoveQuestions",
                             "cancelRemoveQuestions", "executeCreateQuestion", "cancelCreateQuestion",
                             "addQuestionpool", "saveRandomQuestions", "saveQuestionSelectionMode"),
                             "");

                    if (in_array($a_cmd, array('questions', 'createQuestion')) || ($a_cmd == 'removeQuestions' && !$_REQUEST['test_express_mode']))
                            $this->tabs_gui->activateSubTab('edit_test_questions');
		}
                #}

		// print view subtab
		if (!$this->object->isRandomTest())
		{
			$ilTabs->addSubTabTarget("print_view",
				 $this->ctrl->getLinkTarget($this,'print'),
				 "print", "", "", $this->ctrl->getCmd() == 'print');
			$ilTabs->addSubTabTarget('review_view', 
				 $this->ctrl->getLinkTarget($this, 'review'), 
				 'review', '', '', $this->ctrl->getCmd() == 'review');
		}
		
			
	}
	
	function getStatisticsSubTabs()
	{
		global $ilTabs;
		
		// user results subtab
		$ilTabs->addSubTabTarget("eval_all_users",
			 $this->ctrl->getLinkTargetByClass("iltestevaluationgui", "outEvaluation"),
			 array("outEvaluation", "detailedEvaluation", "exportEvaluation", "evalUserDetail", "passDetails",
			 	"outStatisticsResultsOverview", "statisticsPassDetails")
			 , "");
	
		// aggregated results subtab
		$ilTabs->addSubTabTarget("tst_results_aggregated",
			$this->ctrl->getLinkTargetByClass("iltestevaluationgui", "eval_a"),
			array("eval_a"),
			"", "");
	
		// question export
		$ilTabs->addSubTabTarget("tst_single_results",
			$this->ctrl->getLinkTargetByClass("iltestevaluationgui", "singleResults"),
			array("singleResults"),
			"", "");
	}
	
	function getSettingsSubTabs($hiddenTabs = array())
	{
		global $ilTabs, $ilias;
		
		// general subtab
		$ilTabs->addSubTabTarget('general', $this->ctrl->getLinkTargetByClass('ilObjTestSettingsGeneralGUI'),
			 '',											// auto activation regardless from cmd
			 array('ilobjtestsettingsgeneralgui')			// auto activation for ilObjTestSettingsGeneralGUI
		);
                
		if (!in_array('mark_schema', $hiddenTabs)) {
                    // mark schema subtab
                    $ilTabs->addSubTabTarget(
                            "mark_schema",
                            $this->ctrl->getLinkTarget($this,'marks'),
                            array("marks", "addMarkStep", "deleteMarkSteps", "addSimpleMarkSchema",
                                    "saveMarks", "cancelMarks"),
                            array("", "ilobjtestgui", "ilcertificategui")
                    );
                }

		// scoring subtab
		$ilTabs->addSubTabTarget(
			"scoring",
			$this->ctrl->getLinkTarget($this,'scoring'),
			array("scoring"),
			array("", "ilobjtestgui", "ilcertificategui")
		);
	
		// certificate subtab
		include_once "Services/Certificate/classes/class.ilCertificate.php";
		if(ilCertificate::isActive())
		{				
			$ilTabs->addSubTabTarget(
				"certificate",
				$this->ctrl->getLinkTarget($this,'certificate'),
				array("certificate", "certificateEditor", "certificateRemoveBackground", "certificateSave",
					"certificatePreview", "certificateDelete", "certificateUpload", "certificateImport"),
				array("", "ilobjtestgui", "ilcertificategui")
			);
		}

                if (!in_array('defaults', $hiddenTabs)) {
                    // defaults subtab
                    $ilTabs->addSubTabTarget(
                            "tst_default_settings",
                            $this->ctrl->getLinkTarget($this, "defaults"),
                            array("defaults", "deleteDefaults", "addDefaults", "applyDefaults"),
                            array("", "ilobjtestgui", "ilcertificategui")
                    );
                }
	}

	function getParticipantsSubTabs()
	{
		global $ilTabs;

		// participants subtab
		$ilTabs->addSubTabTarget( "participants",
			$this->ctrl->getLinkTarget($this,'participants'),
			array(
				"participants", "saveClientIP",
				"removeParticipant",
				"showParticipantAnswersForAuthor",
				"deleteAllUserResults",
				"cancelDeleteAllUserData", "deleteSingleUserResults",
				"outParticipantsResultsOverview", "outParticipantsPassDetails",
				"showPassOverview", "showUserAnswers", "participantsAction",
				"showDetailedResults",
				'npResetFilter', 'npSetFilter'
			),
			""
		);
		
		if( !$this->testQuestionSetConfigFactory->getQuestionSetConfig()->areDepenciesBroken() )
		{
			// extratime subtab
			$ilTabs->addSubTabTarget( "timing",
				$this->ctrl->getLinkTarget($this,'timingOverview'),
				array("timing", "timingOverview"), "", ""
			);
		}
	}
	
	/**
	* adds tabs to tab gui object
	*
	* @param ilTabsGUI $tabs_gui
	*/
	function getTabs(&$tabs_gui)
	{
		global $ilAccess, $ilUser, $ilHelp;

		if (preg_match('/^ass(.*?)gui$/i', $this->ctrl->getNextClass($this))) {
			return;
		}
		else if ($this->ctrl->getNextClass($this) == 'ilassquestionpagegui') {
			return;
		}
		
		$ilHelp->setScreenIdComponent("tst");
                
		$hidden_tabs = array();
		
		$template = $this->object->getTemplate();
		if($template)
		{
			include_once "Services/Administration/classes/class.ilSettingsTemplate.php";
			$template = new ilSettingsTemplate($template, ilObjAssessmentFolderGUI::getSettingsTemplateConfig());

			$hidden_tabs = $template->getHiddenTabs();
		}
		
		// for local use in this fucking sledge hammer method
		$curUserHasWriteAccess = $ilAccess->checkAccess("write", "", $this->ref_id);
		
		switch( $this->ctrl->getCmdClass() )
		{
			case 'iltestoutputgui':
				
				return; // no tabs .. no subtabs .. during test pass
				
			case 'ilobjtestsettingsgeneralgui':
				
				if( $curUserHasWriteAccess )
				{
					$this->getSettingsSubTabs($hidden_tabs);
				}
				
				break;
		}
		
		switch ($this->ctrl->getCmd())
		{
			case "resume":
			case "previous":
			case "next":
			case "summary":
			case "directfeedback":
			case "finishTest":
			case "outCorrectSolution":
			case "passDetails":
			case "showAnswersOfUser":
			case "outUserResultsOverview":
			case "backFromSummary":
			case "show_answers":
			case "setsolved":
			case "resetsolved":
			case "confirmFinish":
			case "outTestSummary":
			case "outQuestionSummary":
			case "gotoQuestion":
			case "selectImagemapRegion":
			case "confirmSubmitAnswers":
			case "finalSubmission":
			case "postpone":
			case "redirectQuestion":
			case "outUserPassDetails":
			case "checkPassword":
			case "exportCertificate":
			case "finishListOfAnswers":
			case "backConfirmFinish":
			case "showFinalStatement":
				return;
				break;
			case "browseForQuestions":
			case "filter":
			case "resetFilter":
			case "resetTextFilter":
			case "insertQuestions":
				// #8497: resetfilter is also used in lp
				if($this->ctrl->getNextClass($this) != "illearningprogressgui")
				{
					return $this->getBrowseForQuestionsTab($tabs_gui);
				}				
				break;
			case "scoring":
			case "marks":
			case "saveMarks":
			case "cancelMarks":
			case "addMarkStep":
			case "deleteMarkSteps":
			case "addSimpleMarkSchema":
//			case "certificate":
//			case "certificateservice":
//			case "certificateImport":
//			case "certificateUpload":
//			case "certificateEditor":
//			case "certificateDelete":
//			case "certificateSave":
			case "defaults":
			case "deleteDefaults":
			case "addDefaults":
			case "applyDefaults":
			case "inviteParticipants":
			case "searchParticipants":
				if( $curUserHasWriteAccess && in_array($this->ctrl->getCmdClass(), array('ilobjtestgui', 'ilcertificategui')) )
				{
					$this->getSettingsSubTabs($hidden_tabs);
				}
				break;
			case "export":
			case "print":
				break;
			case "statistics":
			case "eval_a":
			case "detailedEvaluation":
			case "outEvaluation":
			case "singleResults":
			case "exportEvaluation":
			case "evalUserDetail":
			case "passDetails":
			case "outStatisticsResultsOverview":
			case "statisticsPassDetails":
				$this->getStatisticsSubTabs();
				break;
		}

		if (strcmp(strtolower(get_class($this->object)), "ilobjtest") == 0)
		{
			// questions tab
			if ($ilAccess->checkAccess("write", "", $this->ref_id) && !in_array('assQuestions', $hidden_tabs))
			{
				$force_active = ($_GET["up"] != "" || $_GET["down"] != "")
					? true
					: false;
				if (!$force_active)
				{
					if ($_GET["browse"] == 1) $force_active = true;
					if (preg_match("/deleteqpl_\d+/", $this->ctrl->getCmd()))
					{
						$force_active = true;
					}
				}

				switch( $this->object->getQuestionSetType() )
				{
					case ilObjTest::QUESTION_SET_TYPE_FIXED:
						$target = $this->ctrl->getLinkTargetByClass('iltestexpresspageobjectgui','showPage');
						break;
					
					case ilObjTest::QUESTION_SET_TYPE_RANDOM:
						$target = $this->ctrl->getLinkTarget($this,'questions');
						if(DEVMODE) $target = $this->ctrl->getLinkTargetByClass('ilTestRandomQuestionSetConfigGUI');
						break;
						
					case ilObjTest::QUESTION_SET_TYPE_DYNAMIC:
						$target = $this->ctrl->getLinkTargetByClass('ilObjTestDynamicQuestionSetConfigGUI');
						break;
				}

				$tabs_gui->addTarget("assQuestions",
					 //$this->ctrl->getLinkTarget($this,'questions'),
					 $target,
					 array("questions", "browseForQuestions", "questionBrowser", "createQuestion", 
					 "randomselect", "filter", "resetFilter", "insertQuestions",
					 "back", "createRandomSelection", "cancelRandomSelect",
					 "insertRandomSelection", "removeQuestions", "moveQuestions",
					 "insertQuestionsBefore", "insertQuestionsAfter", "confirmRemoveQuestions",
					 "cancelRemoveQuestions", "executeCreateQuestion", "cancelCreateQuestion",
					 "addQuestionpool", "saveRandomQuestions", "saveQuestionSelectionMode", "print",
					"addsource", "removesource", "randomQuestions"), 
					 "", "", $force_active);
			}

			// info tab
			if ($ilAccess->checkAccess("visible", "", $this->ref_id) && !in_array('info_short', $hidden_tabs))
			{
				$tabs_gui->addTarget("info_short",
					 $this->ctrl->getLinkTarget($this,'infoScreen'),
					 array("infoScreen", "outIntroductionPage", "showSummary", 
					 "setAnonymousId", "outUserListOfAnswerPasses", "redirectToInfoScreen"));
			}
			
			// settings tab
			if ($ilAccess->checkAccess("write", "", $this->ref_id))
			{
				if (!in_array('settings', $hidden_tabs))
				{
					$settingsCommands = array(
						"marks", "addMarkStep", "deleteMarkSteps", "addSimpleMarkSchema", "saveMarks", "cancelMarks", 
						"certificate", "certificateEditor", "certificateRemoveBackground", "certificateSave",
						"certificatePreview", "certificateDelete", "certificateUpload", "certificateImport",
						"scoring", "defaults", "addDefaults", "deleteDefaults", "applyDefaults",
						"inviteParticipants", "saveFixedParticipantsStatus", "searchParticipants", "addParticipants" // ARE THEY RIGHT HERE
					);
					
					require_once 'Modules/Test/classes/class.ilObjTestSettingsGeneralGUI.php';
					$reflection = new ReflectionClass('ilObjTestSettingsGeneralGUI');
					foreach($reflection->getConstants() as $name => $value)
						if(substr($name, 0, 4) == 'CMD_') $settingsCommands[] = $value;
					
					$settingsCommands[] = ""; // DO NOT KNOW WHAT THIS IS DOING, BUT IT'S REQUIRED
					
					$tabs_gui->addTarget("settings",
						$this->ctrl->getLinkTargetByClass('ilObjTestSettingsGeneralGUI'),
						$settingsCommands, array("ilobjtestsettingsgeneralgui", "ilobjtestgui", "ilcertificategui")
					);
				}

				if (!in_array('participants', $hidden_tabs))
				{
					// participants
					$tabs_gui->addTarget("participants",
						$this->ctrl->getLinkTarget($this,'participants'),
						array(
							"participants", "saveClientIP",
							"removeParticipant", 
							"showParticipantAnswersForAuthor",
							"deleteAllUserResults",
							"cancelDeleteAllUserData", "deleteSingleUserResults",
							"outParticipantsResultsOverview", "outParticipantsPassDetails",
							"showPassOverview", "showUserAnswers", "participantsAction",
							"showDetailedResults", 
							'timing', 'timingOverview', 'npResetFilter', 'npSetFilter', 'showTimingForm'
						),
						""
					);
				}
			}

			include_once './Services/Tracking/classes/class.ilLearningProgressAccess.php';
			if(ilLearningProgressAccess::checkAccess($this->object->getRefId()) && !in_array('learning_progress', $hidden_tabs))
			{
				$tabs_gui->addTarget('learning_progress',
									 $this->ctrl->getLinkTargetByClass(array('illearningprogressgui'),''),
									 '',
									 array('illplistofobjectsgui','illplistofsettingsgui','illearningprogressgui','illplistofprogressgui'));
			}

			if ($ilAccess->checkAccess("write", "", $this->ref_id)  && !in_array('manscoring', $hidden_tabs))
			{
				include_once "./Modules/Test/classes/class.ilObjAssessmentFolder.php";
				$scoring = ilObjAssessmentFolder::_getManualScoring();
				if (count($scoring))
				{
					// scoring tab
					$tabs_gui->addTarget(
							"manscoring", $this->ctrl->getLinkTargetByClass('ilTestScoringGUI', 'showManScoringParticipantsTable'),
							array(
								'showManScoringParticipantsTable', 'applyManScoringParticipantsFilter', 'resetManScoringParticipantsFilter', 'showManScoringParticipantScreen',
								'showManScoringByQuestionParticipantsTable', 'applyManScoringByQuestionFilter', 'resetManScoringByQuestionFilter', 'saveManScoringByQuestion'
								
							), ''
					);
				}
			}

			// Scoring Adjustment
			$setting = new ilSetting('assessment');
			$scoring_adjust_active = (bool) $setting->get('assessment_adjustments_enabled', false);
			if ($ilAccess->checkAccess("write", "", $this->ref_id) && $scoring_adjust_active && !in_array('scoringadjust', $hidden_tabs))
			{
				// scoring tab
				$tabs_gui->addTarget(
					"scoringadjust", $this->ctrl->getLinkTargetByClass('ilScoringAdjustmentGUI', 'showquestionlist'),
					array(
						'showquestionlist', 
						'applyManScoringParticipantsFilter', 
						'saveManScoringByQuestion'
					), ''
				);
			}

			if ((($ilAccess->checkAccess("tst_statistics", "", $this->ref_id)) || ($ilAccess->checkAccess("write", "", $this->ref_id)))  && !in_array('statistics', $hidden_tabs))
			{
				// statistics tab
				$tabs_gui->addTarget("statistics",
					 $this->ctrl->getLinkTargetByClass("iltestevaluationgui", "outEvaluation"),
					 array("statistics", "outEvaluation", "exportEvaluation", "detailedEvaluation", "eval_a", "evalUserDetail",
					 	"passDetails", "outStatisticsResultsOverview", "statisticsPassDetails", "singleResults")
					 , "");
			}

			if ($ilAccess->checkAccess("write", "", $this->ref_id))
			{
                             if (!in_array('history', $hidden_tabs)) {

				// history
				$tabs_gui->addTarget("history",
					 $this->ctrl->getLinkTarget($this,'history'),
					 "history", "");
                             }

                             if (!in_array('meta_data', $hidden_tabs)) {
				// meta data
				$tabs_gui->addTarget("meta_data",
					 $this->ctrl->getLinkTargetByClass('ilmdeditorgui','listSection'),
					 "", "ilmdeditorgui");
                             }

				if(!in_array('export', $hidden_tabs))
				{
					// export tab
					$tabs_gui->addTarget(
						"export",
						 $this->ctrl->getLinkTargetByClass('iltestexportgui' ,''),
						 '',
						 array('iltestexportgui')
					);
				}
			}
			
			if ($ilAccess->checkAccess("edit_permission", "", $this->ref_id)&& !in_array('permissions', $hidden_tabs))
			{
				$tabs_gui->addTarget("perm_settings",
				$this->ctrl->getLinkTargetByClass(array(get_class($this),'ilpermissiongui'), "perm"), array("perm","info","owner"), 'ilpermissiongui');
			}
		}
		
		if( $this->testQuestionSetConfigFactory->getQuestionSetConfig()->areDepenciesBroken() )
		{
			$hideTabs = array(
				'settings', 'manscoring', 'scoringadjust', 'statistics', 'history', 'export'
			);
			
			foreach($hideTabs as $tabId)
			{
				$tabs_gui->removeTab($tabId);
			}
		}
	}
	
	/**
	* Redirect script to call a test with the test reference id
	* 
	* Redirect script to call a test with the test reference id
	*
	* @param integer $a_target The reference id of the test
	* @access	public
	*/
	function _goto($a_target)
	{
		global $ilAccess, $ilErr, $lng;

		if ($ilAccess->checkAccess("visible", "", $a_target))
		{
			//include_once "./Services/Utilities/classes/class.ilUtil.php";
			$_GET["baseClass"] = "ilObjTestGUI";
			$_GET["cmd"] = "infoScreen";
			$_GET["ref_id"] = $a_target;
			include_once("ilias.php");
			exit;
			//ilUtil::redirect("ilias.php?baseClass=ilObjTestGUI&cmd=infoScreen&ref_id=$a_target");
		}
		else if ($ilAccess->checkAccess("read", "", ROOT_FOLDER_ID))
		{
			ilUtil::sendInfo(sprintf($lng->txt("msg_no_perm_read_item"),
				ilObject::_lookupTitle(ilObject::_lookupObjId($a_target))), true);
			ilObjectGUI::_gotoRepositoryRoot();
		}

		$ilErr->raiseError($lng->txt("msg_no_perm_read_lm"), $ilErr->FATAL);
	}

	/**
	 * Questions per page
	 *
	 * @param
	 * @return
	 */
	function buildPageViewToolbar($qid = 0)
	{
		if($this->create_question_mode)
			return;

		global $ilToolbar, $ilCtrl, $lng;

		$this->getQuestionsSubTabs();

		$ilCtrl->saveParameter($this, 'q_mode');

		$ilCtrl->setParameterByClass('iltestexpresspageobjectgui', 'test_express_mode', 1);
		$ilCtrl->setParameter($this, 'test_express_mode', 1);
		$ilCtrl->setParameterByClass('iltestexpresspageobjectgui', 'q_id', $_REQUEST['q_id']);
		$ilCtrl->setParameter($this, 'q_id', $_REQUEST['q_id']);
		$ilToolbar->setFormAction($ilCtrl->getFormActionByClass('iltestexpresspageobjectgui', 'edit'));

		if($this->object->evalTotalPersons() == 0)
		{
			/*
			include_once 'Modules/TestQuestionPool/classes/class.ilObjQuestionPool.php';
			$pool = new ilObjQuestionPool();
			$questionTypes = $pool->getQuestionTypes();$options = array();
			foreach($questionTypes as $label => $data) {
			$options[$data['question_type_id']] = $label;
			}

					include_once("./Services/Form/classes/class.ilSelectInputGUI.php");
					$si = new ilSelectInputGUI($lng->txt("test_add_new_question"), "qtype");
					$si->setOptions($options);
					$ilToolbar->addInputItem($si, true);
			/*
					// use pool
					if ($this->object->isExpressModeQuestionPoolAllowed()) {
						include_once("./Services/Form/classes/class.ilCheckboxInputGUI.php");
						$cb = new ilCheckboxInputGUI($lng->txt("test_use_pool"), "use_pool");
						$ilToolbar->addInputItem($cb, true);
					}
			*/
			$ilToolbar->addFormButton($lng->txt("ass_create_question"), "addQuestion");

			$ilToolbar->addSeparator();

			if($this->object->getPoolUsage())
			{
				$ilToolbar->addFormButton($lng->txt("tst_browse_for_questions"), "browseForQuestions");

				$show_separator = true;
			}
		}

		$questions = $this->object->getQuestionTitlesAndIndexes();

		// desc
		$options = array();
		foreach($questions as $id => $label)
		{
			$options[$id] = $label . ' ['. $this->lng->txt('question_id_short') . ': ' . $id . ']';
		}

		$optionKeys = array_keys($options);

		if(!$options)
		{
			$options[] = $lng->txt('none');
		}
		//else if (count($options) > 1) {
//                    $addSeparator = false;
//                    if ($optionKeys[0] != $qid) {
//                        //$ilToolbar->addFormButton($lng->txt("test_prev_question"), "prevQuestion");
//                        $ilToolbar->addLink($lng->txt("test_prev_question"), $ilCtrl->getLinkTargetByClass('iltestexpresspageobjectgui', 'prevQuestion'));
//                        $addSeparator = true;
//                    }
//		    else {
//			$ilToolbar->addSpacer(45);
//		    }
//
//                    if ($optionKeys[count($optionKeys)-1] != $qid) {
//                        //$ilToolbar->addFormButton($lng->txt("test_next_question"), "nextQuestion");
//                        $ilToolbar->addLink($lng->txt("test_next_question"), $ilCtrl->getLinkTargetByClass('iltestexpresspageobjectgui', 'nextQuestion'));
//			$addSeparator = true;
//                    }
//		    else {
//			$ilToolbar->addSpacer(45);
//		    }
//
//                    //if ($addSeparator) {
//                        $ilToolbar->addSeparator();
//                    //}

		if(count($questions))
		{
			if(isset($show_separator) && $show_separator)
			{
				$ilToolbar->addSeparator();
			}

			$ilToolbar->addLink($lng->txt("test_prev_question"), $ilCtrl->getLinkTargetByClass('iltestexpresspageobjectgui', 'prevQuestion'), !(count($options) > 1 && $optionKeys[0] != $qid));
			$ilToolbar->addLink($lng->txt("test_next_question"), $ilCtrl->getLinkTargetByClass('iltestexpresspageobjectgui', 'nextQuestion'), !(count($options) > 1 && $optionKeys[count($optionKeys) - 1] != $qid));
		}

		if(count($questions) > 1)
		{

			$ilToolbar->addSeparator();

			include_once("./Services/Form/classes/class.ilSelectInputGUI.php");
			$si = new ilSelectInputGUI($lng->txt("test_jump_to"), "q_id");
			$si->addCustomAttribute("onChange=\"forms['ilToolbar'].submit();\"");
			$si->setOptions($options);

			if($qid)
			{
				$si->setValue($qid);
			}

			$ilToolbar->addInputItem($si, true);
		}

		$total = $this->object->evalTotalPersons();

		/*if (count($options)) {
			include_once("./Services/Form/classes/class.ilSelectInputGUI.php");
			$si = new ilSelectInputGUI($lng->txt("test_jump_to"), "q_id");
			$si->addCustomAttribute("onChange=\"forms['ilToolbar'].submit();\"");
			$si->setOptions($options);

			if ($qid) {
				$si->setValue($qid);
			}

			$ilToolbar->addInputItem($si, true);
		}*/

		if(count($questions) && !$total)
		{
			$ilCtrl->setParameter($this, 'q_id', $_REQUEST['q_id']);
			$ilToolbar->addSeparator();
			$ilToolbar->addButton($lng->txt("test_delete_page"), $ilCtrl->getLinkTarget($this, "removeQuestions"));
		}

		if(count($questions) > 1 && !$total)
		{
			$ilToolbar->addSeparator();
			$ilToolbar->addButton($lng->txt("test_move_page"), $ilCtrl->getLinkTarget($this, "movePageForm"));
		}

		global $ilAccess, $ilUser;
		
		$online_access = false;
		if($this->object->getFixedParticipants())
		{
			include_once "./Modules/Test/classes/class.ilObjTestAccess.php";
			$online_access_result = ilObjTestAccess::_lookupOnlineTestAccess($this->object->getId(), $ilUser->getId());
			if($online_access_result === true)
			{
				$online_access = true;
			}
		}

		if($this->object->isOnline() && $this->object->isComplete( $this->testQuestionSetConfigFactory->getQuestionSetConfig() ))
		{
			if((!$this->object->getFixedParticipants() || $online_access) && $ilAccess->checkAccess("read", "", $this->ref_id))
			{
				$testSession = $this->testSessionFactory->getSession();

				$executable = $this->object->isExecutable($testSession, $ilUser->getId(), $allowPassIncrease = TRUE);
				
				if($executable["executable"])
				{
					$player_factory = new ilTestPlayerFactory($this->object);
					$player_instance = $player_factory->getPlayerGUI();

					if ($testSession->getActiveId() > 0)
					{
						$ilToolbar->addSeparator();
						$ilToolbar->addButton($lng->txt('tst_resume_test'), $ilCtrl->getLinkTarget($player_instance, 'resumePlayer'));
					}
					else
					{
						$ilToolbar->addSeparator();
						$ilToolbar->addButton($lng->txt('tst_start_test'), $ilCtrl->getLinkTarget($player_instance, 'startTest'));
					}
				}
			}
		}
	}

	public function copyQuestionsToPoolObject($returnResult = false) {
            //var_dump($_REQUEST);
            include_once 'Modules/TestQuestionPool/classes/class.ilObjQuestionPool.php';
            $qpool = new ilObjQuestionPool($_REQUEST['sel_qpl'], true);
            $qpool->setOnline(ilObjQuestionPool::_lookupOnline($_REQUEST['sel_qpl'], true));

            $newIds = array();
            foreach($_REQUEST['q_id'] as $q_id) {
                $newId = $qpool->copyQuestion($q_id, $qpool->getId());
                $newIds[$q_id] = $newId;
            }

            $result = new stdClass();
            $result->ids = $newIds;
            $result->qpool = $qpool;

            if ($returnResult)
                return $result;
            else
                $this->backObject();
        }

        public function copyAndLinkQuestionsToPoolObject() {
            $result = $this->copyQuestionsToPoolObject(true);
            
            foreach($result->ids as $oldId => $newId) {
                $questionInstance = assQuestion::_instanciateQuestion($oldId);
                $questionInstance->setNewOriginalId($newId);
                $questionInstance->setObjId($result->qpool->getId());
                $questionInstance->saveToDb();

            }
            
            $this->backObject();
        }

        private function getQuestionpoolCreationForm() {
            global $lng;
            include_once 'Services/Form/classes/class.ilPropertyFormGUI.php';
            $form = new ilPropertyFormGUI();

            $title = new ilTextInputGUI($lng->txt('title'), 'title');
            $title->setRequired(true);
            $form->addItem($title);

            $description = new ilTextAreaInputGUI($lng->txt('description'), 'description');
            $form->addItem($description);

            $form->addCommandButton('createQuestionPoolAndCopy', $lng->txt('create'));

            foreach($_REQUEST['q_id'] as $id) {
                $hidden = new ilHiddenInputGUI('q_id[]');
                $hidden->setValue($id);
                $form->addItem($hidden);

            }

            return $form;
        }

        public function copyToQuestionpoolObject() {
            $this->createQuestionpoolTargetObject('copyQuestionsToPool');
        }

        public function copyAndLinkToQuestionpoolObject() {
	    global $lng;

	    include_once "./Modules/TestQuestionPool/classes/class.assQuestion.php";
	    
	    foreach($_REQUEST['q_id'] as $q_id) {
		$questionInstance = assQuestion::_instanciateQuestion($q_id);
		$type = ilObject::_lookupType($questionInstance->getObjId());
		if ($type !== 'tst') {
		    ilUtil::sendFailure($lng->txt('tst_link_only_unassigned'), true);
		    $this->backObject();
		    return;
		}
	    }
	    
            $this->createQuestionpoolTargetObject('copyAndLinkQuestionsToPool');
        }

        public function createQuestionPoolAndCopyObject() {
            $form = $this->getQuestionpoolCreationForm();

	    if ($_REQUEST['title']) {
		    $title = $_REQUEST['title'];
	    }
	    else {
		    $title = $_REQUEST['txt_qpl'];
	    }
	    
	    if (!$title) {
		    ilUtil::sendInfo($this->lng->txt("questionpool_not_entered"));
		    return $this->copyAndLinkToQuestionpoolObject();
	    }
	    
            $ref_id = $this->createQuestionPool($title, $_REQUEST['description']);
            $_REQUEST['sel_qpl'] = $ref_id;

            //if ($_REQUEST['link']) {
                $this->copyAndLinkQuestionsToPoolObject();
            //}
            //else {
            //    $this->copyQuestionsToPoolObject();
            //}
        }

	/**
	* Called when a new question should be created from a test
	* Important: $cmd may be overwritten if no question pool is available
	*
	* @access	public
	*/
	function createQuestionpoolTargetObject($cmd)
	{
		global $ilUser, $ilTabs;
		$this->getQuestionsSubTabs();
		$ilTabs->activateSubTab('edit_test_questions');
                
		$this->tpl->addBlockFile("ADM_CONTENT", "adm_content", "tpl.il_as_tst_qpl_select_copy.html", "Modules/Test");
		$questionpools =& $this->object->getAvailableQuestionpools(FALSE, FALSE, FALSE, TRUE, FALSE, "write");
		if (count($questionpools) == 0)
		{
			$this->tpl->setCurrentBlock("option");
			$this->tpl->setVariable("VALUE_QPL", "");
			$this->tpl->parseCurrentBlock();
		}
		else
		{
			foreach ($questionpools as $key => $value)
			{
				$this->tpl->setCurrentBlock("option");
				$this->tpl->setVariable("VALUE_OPTION", $key);
				$this->tpl->setVariable("TEXT_OPTION", $value["title"]);
				$this->tpl->parseCurrentBlock();
			}
		}
                foreach($_REQUEST['q_id'] as $id) {
                    $this->tpl->setCurrentBlock("hidden");
                    $this->tpl->setVariable("HIDDEN_NAME", "q_id[]");
                    $this->tpl->setVariable("HIDDEN_VALUE", $id);
                    $this->tpl->parseCurrentBlock();
                    $this->tpl->setCurrentBlock("adm_content");
                }
		$this->tpl->setVariable("FORM_ACTION", $this->ctrl->getFormAction($this));

		if (count($questionpools) == 0)
		{
			$this->tpl->setVariable("TXT_QPL_SELECT", $this->lng->txt("tst_enter_questionpool"));
			$cmd = 'createQuestionPoolAndCopy';
		}
		else
		{
			$this->tpl->setVariable("TXT_QPL_SELECT", $this->lng->txt("tst_select_questionpool"));
		}

                $this->tpl->setVariable("CMD_SUBMIT", $cmd);
		$this->tpl->setVariable("BTN_SUBMIT", $this->lng->txt("submit"));
		$this->tpl->setVariable("BTN_CANCEL", $this->lng->txt("cancel"));

                $createForm = $this->getQuestionpoolCreationForm();
                switch($cmd) {
                    case 'copyAndLinkQuestionsToPool':
                        $hidden = new ilHiddenInputGUI('link');
                        $hidden->setValue(1);
                        $createForm->addItem($hidden);
                        break;
                    case 'copyQuestionsToPool':
                        break;
                }
		$createForm->setFormAction($this->ctrl->getFormAction($this));
                #$this->tpl->setVariable('CREATE_QPOOL_FORM', $createForm->getHTML());

		$this->tpl->parseCurrentBlock();
	}

        private function applyTemplate($templateData, $object) {
            // map formFieldName => setterName
            $simpleSetters = array(
                'anonymity' => 'setAnonymity',
                'question_set_type' => 'setQuestionSetType',
                'test_enabled_views' => 'setEnabledViewMode',
                //'express_allow_question_pool' => 'setExpressModeQuestionPoolAllowed',
                'introduction' => 'setIntroduction',
                'showinfo' => 'setShowInfo',
                'finalstatement' => 'setFinalStatement',
                'showfinalstatement' => 'setShowFinalStatement',
                'chb_shuffle_questions' => 'setShuffleQuestions',
                'list_of_questions' => 'setListOfQuestionsSettings',
                'chb_show_marker' => 'setShowMarker',
                'chb_show_cancel' => 'setShowCancel',
                'kiosk' => 'setKiosk',
                'nr_of_tries' => 'setNrOfTries',
                'chb_processing_time' => 'setEnableProcessingTime',
                'chb_use_previous_answers' => 'setUsePreviousAnswers',
                'forcejs' => 'setForceJS',
                'title_output' => 'setTitleOutput',
                'password' => 'setPassword',
                'fixedparticipants' => 'setFixedParticipants',
                'allowedUsers' => 'setAllowedUsers',
                'allowedUsersTimeGap' => 'setAllowedUsersTimeGap',
                'mailnotification' => 'setMailNotification',
                'mailnottype' => 'setMailNotificationType',
                //'' => '',
                'count_system' => 'setCountSystem',
                'mc_scoring' => 'setMCScoring',
                'score_cutting' => 'setScoreCutting',
                'pass_scoring' => 'setScoreReporting',

                'instant_feedback' => 'setScoringFeedbackOptionsByArray',

                'results_presentation' => 'setResultsPresentationOptionsByArray',
                'export_settings' => 'setExportSettings',
                'print_bs_with_res' => 'setPrintBestSolutionWithResult',
            );

	    if (!$templateData['results_presentation']['value']) {
		$templateData['results_presentation']['value'] = array();
	    }

            foreach($simpleSetters as $field => $setter) {
                if($templateData[$field]) {
                    $object->$setter($templateData[$field]['value']);
                }
            }
        }

	public function saveOrderAndObligationsObject()
	{
	    global $ilAccess;
	    if (!$ilAccess->checkAccess("write", "", $this->ref_id))
	    {
		    // allow only write access
		    ilUtil::sendInfo($this->lng->txt("cannot_edit_test"), true);
		    $this->ctrl->redirect($this, "infoScreen");
	    }

	    global $ilCtrl;
		
		$orders = $obligations = array();
		
		foreach($_REQUEST['order'] as $qId => $order)
		{
			$id = (int)str_replace('q_', '', $qId);

			$orders[$id] = $order;
		}
		
		if( $this->object->areObligationsEnabled() && isset($_REQUEST['obligatory']) && is_array($_REQUEST['obligatory']) )
		{
			foreach($_REQUEST['obligatory'] as $qId => $obligation)
			{
				$id = (int)str_replace('q_', '', $qId);

				if( ilObjTest::isQuestionObligationPossible($id) )
				{
					$obligations[$id] = $obligation;
				}
			}
		}
		
	    $this->object->setQuestionOrderAndObligations(
			$orders, $obligations
		);

	    $ilCtrl->redirect($this, 'questions');
	}

	/**
	 * Move current page
	 */
	protected function movePageFormObject()
	{
		global $lng, $ilCtrl, $tpl;

		include_once "Services/Form/classes/class.ilPropertyFormGUI.php";
		$form = new ilPropertyFormGUI();
		$form->setFormAction($ilCtrl->getFormAction($this, "movePage"));
		$form->setTitle($lng->txt("test_move_page"));

		$old_pos = new ilHiddenInputGUI("q_id");
		$old_pos->setValue($_REQUEST['q_id']);
		$form->addItem($old_pos);

		$questions = $this->object->getQuestionTitlesAndIndexes();
		if (!is_array($questions))
		    $questions = array();

		foreach($questions as $k => $q) {
		    if ($k == $_REQUEST['q_id']) {
			unset($questions[$k]);
			continue;
		    }
		    $questions[$k] = $lng->txt('behind') . ' '. $q;
		}
		#$questions['0'] = $lng->txt('first');

		$options = array(
		    0 => $lng->txt('first')
		);
		foreach($questions as $k => $q) {
		    $options[$k] = $q . ' ['. $this->lng->txt('question_id_short') . ': ' . $k  . ']';
		}

		$pos = new ilSelectInputGUI($lng->txt("position"), "position_after");
		$pos->setOptions($options);
		$form->addItem($pos);

		$form->addCommandButton("movePage", $lng->txt("submit"));
		$form->addCommandButton("showPage", $lng->txt("cancel"));

		return $tpl->setContent($form->getHTML());
	}

	public function movePageObject() {
	    global $ilAccess;
	    if (!$ilAccess->checkAccess("write", "", $this->ref_id))
	    {
		    // allow only write access
		    ilUtil::sendInfo($this->lng->txt("cannot_edit_test"), true);
		    $this->ctrl->redirect($this, "infoScreen");
	    }
	    
	    $this->object->moveQuestionAfter($_REQUEST['q_id'], $_REQUEST['position_after']);
	    $this->showPageObject();
	}

	public function showPageObject() {
	    global $ilCtrl;

	    $ilCtrl->setParameterByClass('iltestexpresspageobjectgui', 'q_id', $_REQUEST['q_id']);
	    $ilCtrl->redirectByClass('iltestexpresspageobjectgui', 'showPage');
	}

	public function copyQuestionObject() {
	    global $ilAccess;
	    if (!$ilAccess->checkAccess("write", "", $this->ref_id))
	    {
		    // allow only write access
		    ilUtil::sendInfo($this->lng->txt("cannot_edit_test"), true);
		    $this->ctrl->redirect($this, "infoScreen");
	    }

	    if ($_REQUEST['q_id'] && !is_array($_REQUEST['q_id']))
		$ids = array($_REQUEST['q_id']);
	    else if ($_REQUEST['q_id'])
		$ids = $_REQUEST['q_id'];
	    else
	    {
		ilUtil::sendFailure( $this->lng->txt('copy_no_questions_selected'), true );
		$this->ctrl->redirect($this, 'questions');
	    }

	    $copy_count = 0;

	    $questionTitles = $this->object->getQuestionTitles();

	    foreach($ids as $id)
	    {
		include_once "./Modules/TestQuestionPool/classes/class.assQuestion.php";
		$question = assQuestion::_instanciateQuestionGUI($id);
		if ($question)
		{
		    $title = $question->object->getTitle();
		    $i = 2;
		    while(  in_array( $title . ' (' . $i . ')', $questionTitles ))
			    $i++;

		    $title .= ' (' . $i . ')';

		    $questionTitles[] = $title;

		    $new_id = $question->object->duplicate(false, $title);

		    $clone = assQuestion::_instanciateQuestionGUI($new_id);
		    $clone->object->setObjId($this->object->getId());
		    $clone->object->saveToDb();

		    $this->object->insertQuestion( $this->testQuestionSetConfigFactory->getQuestionSetConfig(), $new_id, true );

		    $copy_count++;
		}
	    }

	    ilUtil::sendSuccess($this->lng->txt('copy_questions_success'), true);

	    $this->ctrl->redirect($this, 'questions');
	}
}