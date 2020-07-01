<?php

/* init variables */
$baseURL = "https://kinto.mozvoice.org/v1/buckets/App/collections/Sentences_Meta_";
$locale  = "zh-HK";
$fileTimeOut = 300; // seconds

function loadLanguages() {
	global $text, $languages;

	$path = getcwd() . "/lang";
	if (!is_dir($path)) {
		return;
	}
	foreach (glob($path . "/*.json") as $filename) {
		$langCode = basename($filename, ".json");
		$text[$langCode] = json_decode(file_get_contents($filename), true);
		$languages[] = $langCode;
	}
}

function t($source) {
	global $text, $locale;

	if (isset($text[$locale])) {
		if (array_key_exists($source, $text[$locale])) {
			return $text[$locale][$source];
		}
	}
	return $source;
}

function getStatistics() {
	global $baseURL, $locale, $fileTimeOut, $statistics;

	if (isset($statistics)) {
		return;
	}
	$fname = sys_get_temp_dir() . "/cv_" . $locale . "-statistics.json";
	if (!file_exists($fname) or (time() - filemtime($fname) > $fileTimeOut)) {
		$statistics = [
			'total'     => 0,
			'approved'  => 0,
			'rejected'  => 0,
			'reviewing' => 0,
		];
		/* total number of sentences */
		$ch = curl_init($baseURL . $locale . "/records");
		curl_setopt($ch, CURLOPT_NOBODY, true);
		curl_setopt($ch, CURLOPT_HEADER, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$res = curl_exec($ch);
		curl_close($ch);

		$headers = explode("\n", $res);
		foreach ($headers as $header) {
			if (substr($header, 0, 15) == "Total-Records: ") {
				$statistics['total'] = substr($header, 15);
				break;
			}
		}

		/* number of approved sentences */
		$ch = curl_init($baseURL . $locale . "/records?approved=true");
		curl_setopt($ch, CURLOPT_NOBODY, true);
		curl_setopt($ch, CURLOPT_HEADER, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$res = curl_exec($ch);
		curl_close($ch);

		$headers = explode("\n", $res);
		foreach($headers as $header) {
			if (substr($header, 0, 15) == "Total-Records: ") {
				$statistics['approved'] = substr($header, 15);
				break;
			}
		}

		/* number of rejcted sentences */
		$ch = curl_init($baseURL . $locale . "/records?approved=false");
		curl_setopt($ch, CURLOPT_NOBODY, true);
		curl_setopt($ch, CURLOPT_HEADER, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$res = curl_exec($ch);
		curl_close($ch);

		$headers = explode("\n", $res);
		foreach($headers as $header) {
			if (substr($header, 0, 15) == "Total-Records: ") {
				$statistics['rejected'] = substr($header, 15);
				break;
			}
		}

		/* number of reviewing sentences */
		$ch = curl_init($baseURL . $locale . "/records?has_approved=false");
		curl_setopt($ch, CURLOPT_NOBODY, true);
		curl_setopt($ch, CURLOPT_HEADER, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$res = curl_exec($ch);
		curl_close($ch);

		$headers = explode("\n", $res);
		foreach ($headers as $header) {
			if (substr($header, 0, 15) == "Total-Records: ") {
				$statistics['reviewing'] = substr($header, 15);
				break;
			}
		}
	} else {
		$statistics = json_decode(file_get_contents($fname));
	}
}

function getSentences() {
	global $baseURL, $locale, $fileTimeOut, $sentences;

	if (isset($sentences)) {
		return;
	}

	$fname = sys_get_temp_dir() . "/cv_" . $locale . "-sentences.json";
	if (!file_exists($fname) or (time() - filemtime($fname) > $fileTimeOut)) {
		$sentences = [];
		$url = $baseURL . $locale . "/records?";
		$url .= "_sort=last_modified";
		$lastModified = 0;
		do {
			$newURL = $url . '&gt_last_modified=' . $lastModified;
			$ch = curl_init($newURL);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			$res = curl_exec($ch);
			curl_close($ch);

			$jres = json_decode($res);
			if (count($jres->data)) {
				$sentences = array_merge($sentences, $jres->data);
				$lastModified = $jres->data[count($jres->data) - 1]->last_modified;
			}
		} while (count($jres->data) == 10000);
		file_put_contents($fname, json_encode($sentences));
	} else {
		$sentences = json_decode(file_get_contents($fname));
	}
}

function filterEQ($sentences, $field, $value) {
	$result = [];
	foreach ($sentences as $sentence) {
		if (property_exists($sentence, $field) and ($sentence->{$field} == $value)) {
			$result[] = $sentence;
		}
	}
	return $result;
}

function filterGT($sentences, $field, $value) {
	$result = [];
	foreach ($sentences as $sentence) {
		if (property_exists($sentence, $field) and ($sentence->{$field} > $value)) {
			$result[] = $sentence;
		}
	}
	return $result;
}

function filterLT($sentences, $field, $value) {
	$result = [];
	foreach ($sentences as $sentence) {
		if (property_exists($sentence, $field) and ($sentence->{$field} < $value)) {
			$result[] = $sentence;
		}
	}
	return $result;
}

function filterNE($sentences, $field, $value) {
	$result = [];
	foreach ($sentences as $sentence) {
		if (property_exists($sentence, $field) and ($sentence->{$field} != $value)) {
			$result[] = $sentence;
		}
	}
	return $result;
}

function filterIN($sentences, $field, $values) {
	$result = [];
	foreach ($sentences as $sentence) {
		if (property_exists($sentence, $field) and in_array($sentence->{$field}, $value)) {
			$result[] = $sentence;
		}
	}
	return $result;
}

function filterExists($sentences, $field) {
	$result = [];
	foreach ($sentences as $sentence) {
		if (property_exists($sentence, $field)) {
			$result[] = $sentence;
		}
	}
	return $result;
}

function filterNotExists($sentences, $field) {
	$result = [];
	foreach ($sentences as $sentence) {
		if (!property_exists($sentence, $field)) {
			$result[] = $sentence;
		}
	}
	return $result;
}

function groupBy($sentences, $field) {
	$result = [];
	foreach ($sentences as $sentence) {
		if (property_exists($sentence, $field)) {
			if (array_key_exists($sentence->{$field}, $result)) {
				$result[$sentence->{$field}]++;
			} else {
				$result[$sentence->{$field}] = 1;
			}
		}
	}
	return $result;
}

function groupByApprover($sentences) {
	$result = [];
	foreach ($sentences as $sentence) {
		if (property_exists($sentence, "valid")) {
			foreach ($sentence->valid as $username) {
				if (array_key_exists($username, $result)) {
					$result[$username]++;
				} else {
					$result[$username] = 1;
				}
			}
		}
		if (property_exists($sentence, "invalid")) {
			foreach ($sentence->invalid as $username) {
				if (array_key_exists($username, $result)) {
					$result[$username]++;
				} else {
					$result[$username] = 1;
				}
			}
		}
	}
	return $result;
}

loadLanguages();
if (isset($_GET['lang']) and in_array($_GET['lang'], $languages)) {
	$locale = $_GET['lang'];
}
getStatistics();
getSentences();

/*** Contributor statistics ***/
$contributors = groupBy($sentences, "username");
arsort($contributors);

/*** Reviewer statistics ***/
$reviewers = groupByApprover($sentences);
arsort($reviewers);
?>
<!doctype html>
<html lang="en">
  <head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css" integrity="sha384-9aIt2nRpC12Uk9gS9baDl411NQApFmC26EwAOH8WgZl5MYYxFfc+NcPb1dKGj7Sk" crossorigin="anonymous">

    <title>Sentence Collector Statistics</title>
  </head>
  <body class="container" style="background-color: #ddd;">
    <h1 class="text-center"><?php echo t("Sentence Collector Dashboard"); ?></h1>
    <div class="row">
      <div class="col">
        <form method="get">
          <select class="custom-select mb-3" name="lang" onchange="this.form.submit();">
	    <?php foreach ($languages as $lang) {
               echo "<option value=\"" . $lang . "\"";
               if ($lang == $locale) { echo " selected";}
               echo ">" . $lang . "</option>\n";
             } ?>
          </select>
        </form>
        <div class="card text-center">
          <ul class="list-group list-group-flush">
            <li class="list-group-item">
              <h3><?php echo $statistics['total'];?></h3>
              <p><?php echo t("sentences submitted"); ?></p>
            </li>
            <li class="list-group-item">
              <h3 class="card-title" style="color: #0d0;"><?php echo $statistics['approved'];?></h3>
              <p class="card-text"><?php echo t("sentences approved"); ?></p>
            </li>
            <li class="list-group-item">
              <h3 class="card-title" style="color: #f00;"><?php echo $statistics['rejected'];?></h3>
              <p class="card-text"><?php echo t("sentences rejected"); ?></p>
            </li>
            <li class="list-group-item">
              <h3 class="card-title" style="color: #ffa500;"><?php echo $statistics['reviewing'];?></h3>
              <p class="card-text"><?php echo t("sentences being reviewed"); ?></p>
            </li>
          </ul>
        </div>
      </div>
      <div class="col-5">
        <div class="card">
          <h3 class="card-header card-title"><?php echo t("Top Contributors"); ?></h3>
          <div class="card-body" style="height: 400px;">
            <ul class="list-group overflow-auto h-100">
              <?php $i = 0; foreach ($contributors as $user => $count) { $i++; ?>
              <li class="list-group-item d-flex justify-content-between align-items-center">
                <?php echo $i . ". " . $user;?>
                <span class="badge badge-primary badge-pill"><?php echo $count;?></span>
              </li>
              <?php } ?>
            </ul>
          </div>
        </div>
      </div>
      <div class="col-5">
         <div class="card">
           <h3 class="card-header card-title"><?php echo t("Top Reviewers"); ?></h3>
           <div class="card-body" style="height: 400px;">
             <ul class="list-group overflow-auto h-100">
               <?php $i = 0; foreach ($reviewers as $user => $count) { $i++; ?>
               <li class="list-group-item d-flex justify-content-between align-items-center">
                 <?php echo $i . ". " . $user;?>
                 <span class="badge badge-primary badge-pill"><?php echo $count;?></span>
               </li>
               <?php } ?>
             </ul>
           </div>
         </div>
      </div>
    </div>
    <!-- Optional JavaScript -->
    <!-- jQuery first, then Popper.js, then Bootstrap JS -->
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js" integrity="sha384-DfXdz2htPH0lsSSs5nCTpuj/zy4C+OGpamoFVy38MVBnE+IbbVYUew+OrCXaRkfj" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js" integrity="sha384-Q6E9RHvbIyZFJoft+2mJbHaEWldlvI9IOYy5n3zV9zzTtmI3UksdQRVvoxMfooAo" crossorigin="anonymous"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js" integrity="sha384-OgVRvuATP1z7JjHLkuOU7Xw704+h835Lr+6QL9UvYjZE3Ipu6Tp75j7Bh/kR0JKI" crossorigin="anonymous"></script>
  </body>
</html>
