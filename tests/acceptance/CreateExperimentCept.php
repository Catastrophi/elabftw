<?php declare(strict_types=1);
/**
 * @author Nicolas CARPi <nicolas.carpi@curie.fr>
 * @copyright 2012 Nicolas CARPi
 * @see https://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */
$I = new AcceptanceTester($scenario);
$I->wantTo('Test creating an experiment');
testLogin($I);
$I->amOnPage('experiments.php');
$I->see('Experiments');
$I->amOnPage('experiments.php?create=true');
$I->see('Tags');
$I->see('Date');
$I->see('Title');
$I->wantTo('Change the body of the experiment');
$I->executeJS("tinyMCE.activeEditor.setContent('supercalifragilisticexpialidocious');");
$I->click('/html/body/section/div/section[1]/form/div[3]/button');
$I->seeInDatabase('experiments', array('body' => '<p>supercalifragilisticexpialidocious</p>'));
$I->wantTo('Change the visibility to user');
$I->amOnPage('experiments.php?mode=edit&id=1');
$I->selectOption('#visibility_select', 'Only me');
$I->waitForJS('return jQuery.active == 0', 10);
$I->amOnPage('experiments.php?mode=view&id=1');
$I->see('User');
