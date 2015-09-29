<?php
// Copyright © 2015 Jamie Harrell <jharrell@gmail.com>
//
// Licensed under the Simple Public License, Version 2.0 (the "License");
// you may not use this file except in compliance with the License.
// You may obtain a copy of the License at
// http://opensource.org/licenses/Simple-2.0
//
// Unless required by applicable law or agreed to in writing, software
// distributed under the License is distributed on an "AS IS" BASIS,
// WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
// See the License for the specific language governing permissions and
// limitations under the License.

include_once("./config.php");

session_start();
$gData = array();
$gXML = array();
$gExpan = array();
$gCounts = array();
$uIsAdmin = false;

// What pages are currently valid to access, with the value being the icon used in the menu
$gValidPages = array(
  "owned" => "check",
  "wanted" => "list",
  "tried" => "question",
  "admin" => ""
);

// Category icons for the list
$gHCColors = array(
  "Party Game" => "users",
  "Bluffing" => "user-secret",
  "Card Game" => "heart",
  "Trivia" => "graduation-cap",
  "Fighting" => "fighter-jet",
  "Dice" => "cube",
  "Animals" => "hand-lizard-o",
  "Pirates" => "ship",
  "Civilization" => "globe",
  "Science Fiction" => "hand-spock-o",
  "City Building" => "building",
  "Horror" => "frown-o",
  "Medieval" => "shield",
  "Fantasy" => "shield",
  "Abstract Strategy" => "balance-scale",
  "Medical" => "ambulence",
  "Industry / Manufacturing" => "industry",
  "Economic" => "money",
  "Travel" => "map-pin",
  "Action / Dexterity" => "bicycle",
  "Wargame" => "bomb",
);

// Breakpoints for letters.
$gLetterBreaks = array(
  "1" => "abc",
  "2" => "def",
  "3" => "ghi",
  "4" => "jkl",
  "5" => "mno",
  "6" => "pqrs",
  "7" => "tuv",
  "8" => "wxyz"
);

if(isset($_GET["logout"])) {
  unset($_SESSION['uIsAdmin']);
  $_SESSION['uIsAdmin'] = "bollocks";
}

if(!isset($_GET["page"]) || !array_key_exists($_GET["page"],$gValidPages)) {
  $pageID = "owned";
} else {
  $pageID = $_GET["page"];
}

if(!isset($_GET["letters"]) || !array_key_exists(trim($_GET["letters"]),$gLetterBreaks)) {
  $letterID = 1;
} else {
  $letterID = $_GET["letters"];
}

if(isset($_GET["t"])) {
  $tagID = $_GET["t"];
}

if(isset($_POST["game_adm_pwd"]) && (trim($_POST["game_adm_pwd"]) === $ggAdminPass)) {
  $_SESSION['uIsAdmin'] = password_hash($ggAdminPass,PASSWORD_DEFAULT);
}

function generateFormToken($form) {
  $token = hash("sha256",uniqid(microtime(),true));
  $_SESSION[$form.'_token'] = $token;
  return $token;
}

function verifyFormToken($form) {
  if(!isset($_SESSION[$form.'_token'])) {
    return false;
  }
  if(!isset($_POST['token'])) {
    return false;
  }
  if($_SESSION[$form.'_token'] !== $_POST['token']) {
    return false;
  }
  return true;
}

function isPageAdmin() {
  return (password_verify($GLOBALS['ggAdminPass'],$_SESSION['uIsAdmin']));
}

function loadExpans() {
  $lExpans = fopen("./list.expansions",r);
  $lArr = array();
  while($curLine = fgets($lExpans)) {
    array_push($lArr,trim($curLine));
  }
  $GLOBALS['gExpan'] = $lArr;
}

function loadJSONData($whatFile) {
  $fName = "./list.".$whatFile.".json";
  $lJSON = file_get_contents($fName);
  $GLOBALS['gData'] = json_decode($lJSON);
}

function loadTotalCounts() {
  $lJSON = array();
  foreach($GLOBALS['gValidPages'] as $cPage => $cIcon) {
    if($cPage !== "admin") {
      $fName = "./list.".$cPage.".json";
      $lJSON = json_decode(file_get_contents($fName));
      $GLOBALS['gCounts'][$cPage] = count($lJSON);
    }
  }
}

function handleDiffs() {
  $fName = "./list.".$_POST['processedPage'].".json";
  $fHandle = fopen($fName,'w');
  $tArr = array();
  $tC = 0;

  foreach($GLOBALS['gData'] as $key => $cData) {
    if(isset($_POST[$cData->bggid . "-delete"]) && ($_POST[$cData->bggid . "-delete"] == "on")) {
      unset($GLOBALS['gData'][$key]);
      continue;
    }

    if(isset($_POST[$cData->bggid . "-played"]) && ($_POST[$cData->bggid . "-played"] == "on")) {
      $cData->played = 1;
    }

    if(isset($_POST[$cData->bggid . "-cost"]) && ($_POST[$cData->bggid . "-cost"] != $cData->cost)) {
      $cData->cost = $_POST[$cData->bggid . "-cost"];
    }

    if(isset($_POST[$cData->bggid . "-costwhere"]) && ($_POST[$cData->bggid . "-costwhere"] != $cData->costwhere)) {
      $cData->costwhere = $_POST[$cData->bggid . "-costwhere"];
    }

    if(isset($_POST[$cData->bggid . "-howFound"]) && ($_POST[$cData->bggid . "-howFound"] != $cData->howFound)) {
      $cData->howFound = $_POST[$cData->bggid . "-howFound"];
    }

    if(isset($_POST[$cData->bggid . "-notes"]) && ($_POST[$cData->bggid . "-notes"] != $cData->notes)) {
      $cData->notes = $_POST[$cData->bggid . "-notes"];
    }

    foreach($GLOBALS['ggUsers'] as $cUser) {
      if(isset($_POST[$cData->bggid."-notes".$cUser]) && ($_POST[$cData->bggid."-notes".$cUser] != $cData->notes->$cUser)) {
        $cData->notes->$cUser = $_POST[$cData->bggid."-notes".$cUser];
      }
    }

    $tArr[$tC] = $cData;
    $tC++;
  }

  if(isset($_POST['additem-new'])) {
    $lTmp = new stdClass();
    $lTmp->bggid = $_POST['additem-bggid'];
    $lTmp->title = $_POST['additem-title'];
    if($_POST['additem-played'] == "on") { $lTmp->played = 1; }
    if(isset($_POST['additem-cost'])) { $lTmp->cost = $_POST['additem-cost']; }
    if(isset($_POST['additem-costwhere'])) { $lTmp->costwhere = $_POST['additem-costwhere']; }
    if(isset($_POST['additem-howFound'])) { $lTmp->howFound = $_POST['additem-howFound']; }
    if(isset($_POST['additem-notes'])) { $lTmp->notes = $_POST['additem-notes']; }
    foreach($GLOBALS['ggUsers'] as $cUser) {
      if(isset($_POST['additem-notes'.$cUser])) { $lTmp->notes->$cUser = $_POST['additem-notes'.$cUser]; }
    }

    $tArr[$tC] = $lTmp;
  }

  fwrite($fHandle,json_encode($tArr,JSON_PRETTY_PRINT));
  fflush($fHandle);
  fclose($fHandle);
}

function loadBGGData($letters) {
  $baseurl = "http://www.boardgamegeek.com/xmlapi2/thing?id=";

  foreach($GLOBALS['gData'] as $cGame) {
    $numsFirst = 0;
    if((is_numeric($cGame->title[0])) && $letters == 1) {
      $numsFirst = 1;
    }
    if($GLOBALS['ggSplitByLetters'] && ((strpos($GLOBALS['gLetterBreaks'][$letters],strtolower($cGame->title[0]))) === false) && !$numsFirst ) {
      continue;
    }
    $baseurl = $baseurl . $cGame->bggid . ",";

  }

  $xml = simplexml_load_file($baseurl);

  $GLOBALS['gXML'] = $xml;
}

if(isPageAdmin()) {
  $ggSplitByLetters = false;
}

function printSplits($page) {
  if($GLOBALS['ggSplitByLetters'] && ($GLOBALS['pageID'] == $page)) {
    print("<div class=\"letterIDs\">");
    foreach($GLOBALS['gLetterBreaks'] as $key => $cBreak) {
      print("<a href=\"./?page=".$GLOBALS['pageID']."&amp;letters=".$key."\" class=\"letterTags".(($GLOBALS['letterID'] == $key) ? ' letterCurrent' : '')."\">[");
      if($key==1){
        $cPrint = "#".$cBreak;
      } else {
        $cPrint = $cBreak;
      }
      print(strtoupper(($cPrint)));
      print("]</a>");
    }
    print("</div>");
  }
}

if($pageID != "admin") {
  loadJSONData($pageID);
  loadExpans();
  loadTotalCounts();

  usort($gData, function($a, $b) { return strcmp($a->title, $b->title); });

  if(!ggAutoLetters || (count($gData) < $ggAutoLetters)) {
    $ggSplitByLetters = false;
  }

  if(!isPageAdmin()) {
    loadBGGData($letterID);
  }

} elseif (isset($_POST['processedPage']) && isPageAdmin()) {
  loadJSONData($_POST['processedPage']);
  handleDiffs();
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <title><?php print($GLOBALS['ggTitle']);?></title>
    <meta name="description" content="<?php print($GLOBALS['ggTitle']); ?>">
    <meta name="author" content="Jamie Harrell">
    <link href='https://fonts.googleapis.com/css?family=Roboto:400,300,500,700,900,100' rel='stylesheet' type='text/css'>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.4.0/css/font-awesome.min.css">
    <link href='./style.css' rel='stylesheet'>
  </head>
  <body>
    <div class="sidebar-wrapper">
      <div class="sidebar-head"><i class="fa fa-bars fa-fw"></i> <a href="./">Menu</a></div>
      <nav>
      <?php
        foreach($GLOBALS['gValidPages'] as $cPage => $cIcon) {
          if($cPage == "admin") { continue; }
          else {
      ?>
        <a href="./?page=<?php print($cPage); ?>"<?php if($GLOBALS['pageID'] == $cPage) { print(" class=\"active\""); } ?>><i class="fa fa-<?php print($cIcon); ?> fa-fw"></i> <?php print(ucwords($cPage));?> <span>(<?php print($GLOBALS['gCounts'][$cPage]);?>)</span></a>
      <?php
            printSplits($cPage);
          } // if else
        } //foreach
      ?>
      <?php
        if(isPageAdmin()) {
      ?>
        <a href="./?logout=1"<?php if($GLOBALS['pageID'] == "tried") { print(" class=\"active\""); } ?>><i class="fa fa-lock fa-fw admin_flag"></i>Logout</a>
      <?php
        } else {
      ?>
        <a href="./?page=admin"<?php if($GLOBALS['pageID'] == "admin") { print(" class=\"active\""); } ?>><i class="fa fa-wrench fa-fw<?php if(isPageAdmin()){print(" admin_flag");}?>"></i> Admin</a>
      <?php } //if else ?>
      </nav>
    </div>
    <div class="main-wrapper">
      <header>
        <div>
          <h1 style="text-align: center;"><?php print($GLOBALS['ggTitle']);?></h1>
        </div>
      </header>
      <div class="counter">
      <?php
        if($GLOBALS['pageID'] == "admin") {
          if(!isPageAdmin()) {
      ?>
        Enter your admin password:
        <form method="post" action="./">
          <input type="password" name="game_adm_pwd" style="width: 20%">
          <input class="button" type="submit" name="submit_adm" value="Submit">
        </form>
      <?php
          } else {
            if(isset($_POST['processedPage'])) {
      ?>
              <div class="passing"><i class="fa fa-fw fa-check"></i> Game data written to <?php print($_POST['processedPage']); ?>.</div>
      <?php
            } // if
          } // if else else
        } //if admin
      ?>
      </div>
      <?php
        if(isPageAdmin() && ($GLOBALS['pageID'] !== "admin")) {
      ?>
      <form method="post" action="./?page=admin">
        <input type="hidden" name="processedPage" value="<?php print($GLOBALS['pageID']); ?>">
        <table class="adminTable">
          <thead>
            <tr>
              <th class="c-xsmall">Delete?</th><th class="c-small">ID</th><th class="c-medium">Title</th>
              <?php
                switch($GLOBALS['pageID']) {
                  case "owned":
                    print("<th>Notes</th>");
                    break;
                  case "wanted":
                    print("<th class=\"c-xsmall\">Played?</th>");
                    print("<th class=\"c-xsmall\">Cost</th>");
                    print("<th class=\"c-small\">Where at?</th>");
                    print("<th class=\"c-small\">How Found?</th>");
                    print("<th>Notes</th>");
                    break;
                  case "tried":
                    foreach($GLOBALS['ggUsers'] as $cUser) {
                      print("<th>".$cUser."'s Notes\"</th>");
                    }
                    break;
                } // switch
              ?>
            </tr>
          </thead>
          <tbody>
          <?php
            foreach($GLOBALS['gData'] as $cData) {
          ?>
            <tr>
              <td><input type="checkbox" name="<?php print($cData->bggid."-delete"); ?>"></td><td><?php print($cData->bggid); ?></td><td><?php print($cData->title); ?></td>
              <?php
                switch($GLOBALS['pageID']) {
                  case "owned":
                    print("<td><input type=\"text\" value=\"".$cData->notes."\" name=\"".$cData->bggid."-notes\"></td>");
                    break;
                  case "wanted":
                    print("<td><input type=\"checkbox\" name=\"".$cData->bggid."-played\".".($cData->played ? ' checked': '')."></td>");
                    print("<td><input type=\"text\" name=\"".$cData->bggid."-cost\" value=\"".$cData->cost."\"></td>");
                    print("<td><input type=\"text\" name=\"".$cData->bggid."-costwhere\" value=\"".$cData->costwhere."\"></td>");
                    print("<td><input type=\"text\" name=\"".$cData->bggid."-howFound\" value=\"".$cData->howFound."\"></td>");
                    print("<td><input type=\"text\" name=\"".$cData->bggid."-notes\" value=\"".$cData->notes."\"></td>");
                    break;
                  case "tried":
                    foreach($cData->notes as $index => $cUser) {
                      print("<td><input type=\"text\" name=\"".$cData->bggid."-notes".$index."\" value=\"".$cUser."\"></td>");
                    }
                    break;
                } //switch
              ?>
            </tr>
          <?php
            } // foreach
          ?>
          </tbody>
          <thead><tr><th>New</th></tr></thead>
          <tbody>
            <tr>
              <td><input type="checkbox" name="additem-new"></td><td><input type="text" value="" name="additem-bggid"></td><td><input type="text" value="" name="additem-title"></td>
              <?php
                switch($GLOBALS['pageID']) {
                  case "owned":
                    print("<td><input type=\"text\" name=\"additem-notes\"></td>");
                    break;
                  case "wanted":
                    print("<td><input type=\"checkbox\" name=\"additem-played\"></td>");
                    print("<td><input type=\"text\" name=\"additem-cost\"></td>");
                    print("<td><input type=\"text\" name=\"additem-costwhere\"></td>");
                    print("<td><input type=\"text\" name=\"additem-howFound\"></td>");
                    print("<td><input type=\"text\" name=\"additem-notes\"></td>");
                    break;
                  case "tried":
                    foreach($GLOBALS['ggUsers'] as $cUser) {
                      print("<td><input type=\"text\" name=\"additem-notes".$cUser."\"></td>");
                    }
                    break;
                } //switch
              ?>
            </tr>
          </tbody>
        </table>
        <div class="btncontainer">
          <input class="button" type="submit" id="submit" value="Submit">
        </div>
      </form>
      <?php
        } else if($GLOBALS['pageID'] != "admin") {
          if(count($GLOBALS['gXML']) > 0) {
            foreach($GLOBALS['gXML'] as $cXML) {
              $catFound = false;
              if(isset($GLOBALS['tagID'])) {
                foreach($cXML->link as $cLink) {
                  if($cLink->attributes()->type == "boardgamecategory") {
                    if(strcasecmp(trim($cLink->attributes()->value), $GLOBALS['tagID']) === 0) {
                      $catFound = TRUE;
                    }
                  }
                }
                if($catFound == false) {
                  continue;
                }
              }
      ?>
      <div class="gamewrapper">
      <div class="gametitle">
        <?php
          foreach($cXML->link as $cLink) {
            if($cLink->attributes()->type == "boardgamecategory") {
              if(array_key_exists(trim($cLink->attributes()->value),$GLOBALS['gHCColors'])) {
                print("<i class=\"fa fa-");
                print($GLOBALS['gHCColors'][trim($cLink->attributes()->value)]);
                print("\"></i> ");
                break 1;
              }
            }
          }
        ?>
        <?php print($cXML->name[0]->attributes()->value); ?> (<?php print($cXML->yearpublished->attributes()->value); ?>)
      </div>
      <article class="gamedescription"><h4>Description</h4><?php print($cXML->description);?></article>
      <aside class="aside gameimg"><img src="<?php print($cXML->thumbnail);?>" alt="<?php print($cXML->name[0]->attributes()->value);?>"></aside>
      <aside class="aside gamestats">
        <div class="dataTag"><i class="fa fa-fw fa-2x fa-clock-o"></i><span class="dataValue"><?php print($cXML->playingtime->attributes()->value);?></span></div>
        <div class="dataTag"><i class="fa fa-fw fa-2x fa-user-plus"></i><span class="dataValue"><?php print($cXML->minplayers->attributes()->value);?> - <?php print($cXML->maxplayers->attributes()->value);?></span></div>
        <?php
          if($GLOBALS['pageID'] == "wanted") {
        ?>
          <div class="dataTag"><i class="fa fa-fw fa-2x fa-play"></i><span class="dataValue"><?php
            foreach($GLOBALS['gData'] as $cData) {
              if(strcasecmp(trim($cXML->name[0]->attributes()->value),$cData->title) == 0) {
                if($cData->played) { print("Yes"); } else { print("No"); }
              }
            }
          ?></span></div>
          <div class="dataTag"><i class="fa fa-fw fa-2x fa-search"></i><span class="dataValue"><?php
            foreach($GLOBALS['gData'] as $cData) {
              if(strcasecmp(trim($cXML->name[0]->attributes()->value),$cData->title) == 0) {
                print($cData->howFound);
              }
            } //foreach
          ?></span></div><br />
          <div class="dataTag"><i class="fa fa-fw fa-2x fa-dollar"></i><span class="dataValue"><?php
            foreach($GLOBALS['gData'] as $cData) {
              if(strcasecmp(trim($cXML->name[0]->attributes()->value),$cData->title) == 0) {
                print($cData->cost);
                print(" <br/> ");
                print($cData->costwhere);
              }
            } //foreach
          ?></span></div>
        <?php } ?>
        <div class="tagcontainer"><i class="fa fa-fw fa-2x fa-tag"></i> <b><?php
          $linkFirst = 0;
          foreach($cXML->link as $cLink) {
            if($cLink->attributes()->type == "boardgamecategory") {
              if($linkFirst != 0) {
                print(" ");
              }
              $linkFirst++;
              print("<a class=\"tag\" href=\"?page=".$GLOBALS['pageID']."&amp;t=".urlencode($cLink->attributes()->value)."\">");
              print($cLink->attributes()->value);
              print("</a>");
              if(!($linkFirst % 2)){
                print("<br>");
              }
            }
          } // foreach
          ?> </b>
        </div>
      </aside>
      <div class="gameexpan">
        <?php
          $numExpans = 0;
          foreach($cXML->link as $cLink) {
            if($cLink->attributes()->type == "boardgameexpansion") {
              if($numExpans == 0) {
                print("<input type=\"checkbox\" id=\"".$cXML->attributes()->id."-expancheck\">");
                print("<label for=\"".$cXML->attributes()->id."-expancheck\">Expansions</label><div>");
                print("<table><tr>");
              } else if (($numExpans % 2) == 0) {
                print("<tr>");
              }
              $numExpans++;
              print("<td>");
              if(in_array(trim($cLink->attributes()->id),$GLOBALS['gExpan'])) {
                print(" <i class=\"fa fa-fw fa-check\" style=\"color: #43a047\"></i>");
              } else {
                print(" <i class=\"fa fa-fw fa-times\" style=\"color: #e53935\"></i>");
              }
              print($cLink->attributes()->value);
              print("</td>");
              if(!($numExpans % 2)) {
                print("</tr>");
              }
            }
          } //foreach
        if($numExpans > 0) {
          print("</table></div>");
        }
        ?>
      </div>
      <footer class="gamenotes">
        <?php
          foreach($GLOBALS['gData'] as $cData) {
            if(strcasecmp(trim($cXML->name[0]->attributes()->value),$cData->title) == 0) {
              if($GLOBALS['pageID'] == "tried"){
            foreach($GLOBALS['ggUsers'] as $cUser) {
            print($cUser.": ".$cData->notes->$cUser."<br>");
          }
              } else if($cData->notes == "") {
                print("No comment set yet");
              } else {
                print($cData->notes);
              }
            }
          } //foreach
        ?>
      </footer>
    </div>
    <?php
      } //foreach gXML
    } else {
    ?>
    <div class="error"><i class="fa fa-fw fa-warning"></i> No games found.</div>
    <?php
      } //if for empty gxml
    } // if not page == admin
    ?>
  </div>
  <?php
    if($GLOBALS['ggSplitByLetters']) {
  ?>
  <div class="nav-buttons">
    <?php
      $cLetter = $GLOBALS['letterID'] - 1;
      if(array_key_exists($cLetter,$GLOBALS['gLetterBreaks'])) {
    ?>
      <a href="./?page=<?php print($GLOBALS['pageID']); ?>&amp;letters=<?php print($cLetter);?>" class="button"><i class="fa fa-fw fa-caret-left"></i></a>
    <?php
      }
    ?>
    <?php
      $cLetter = $GLOBALS['letterID'] + 1;
      if(array_key_exists($cLetter,$GLOBALS['gLetterBreaks'])) {
    ?>
      <a href="./?page=<?php print($GLOBALS['pageID']); ?>&amp;letters=<?php print($cLetter);?>" class="button"><i class="fa fa-fw fa-caret-right"></i></a>
    <?php
      }
    ?>
   </div>
   <?php
   }
   ?>
    <footer>
      <a href="//github.com/peoii/SimpliGames/"><i class="fa fa-fw fa-github"></i><span class="collapse-foot"> Github</span></a> &middot;
      <a href="//github.com/peoii/SimpliGames/issues"><i class="fa fa-fw fa-code-fork"></i><span class="collapse-foot"> Issue Tracker</span></a> &middot;
      Created By <a href="//jamie.harrell.ca/">@peoii</a><br />
      <sub>Powered by <a href="//www.boardgamegeek.com/">Board Game Geek</a></sub>
    </footer>
    <!--
     <?php print_r($cXML); ?>
    -->
  </body>
</html>
