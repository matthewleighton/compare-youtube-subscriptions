<link href="https://fonts.googleapis.com/css?family=Raleway" rel="stylesheet">
<style>
	/* Results List */
	li {
		list-style-type: none;
		text-align: left;
	}

	a {
		text-decoration: none;
		color: #0000EE;
	}

	a:hover {
		text-decoration: underline;
	}

	.container {
		text-align: center;
	}

	.section-title {
		font-family: 'Raleway', sans-serif;
		text-align: center;
	}

	.user-entry {
		display: inline-block;
	}

	.results {
		display: inline-block;
		margin: 0 auto;
		min-width: 300px;
		vertical-align: top;
	}

	.channel-thumbnail {
		max-height: 40px;
		vertical-align: middle;
	}

	/* Submission Form */
	form {
		display: inline-block;
		margin-top: 20px;
		text-align: left;
	}

	label {
		display: inline-block;
		margin-bottom: 10px;
	}

	h2 {
		text-align: center;
	}

	.form-title {
		font-family: 'Raleway', sans-serif;
		text-align: center;
	}

	.form-submit {
		margin-top: 20px;
		text-align: center;
	}

	input[type="submit"] {
		width: 35%;
	}

	.how-to-use {
		margin: auto;
		margin-top: 40px;
		font-family: 'Raleway', sans-serif;
		width: 470px;
	}
</style>

<?php

if (count($_FILES) == 2 && !empty($_FILES['user1-file']['tmp_name']) && !empty($_FILES['user2-file']['tmp_name'])) {
	$counter = new subscription_counter;
	$result = $counter->runCounter();
} else {
	?>
	<div class="container">
		<form method="POST" enctype="multipart/form-data">
		<h1 class="form-title">YouTube Subscriptions Comparator</h1>
			<div class="user-entry">
				<h2 class="form-title">User One</h2>
				<label>Name: </label><input type="text" name="user1-name"><br/>
				<label>XML File: </label><input type="file" name="user1-file">
			</div>

			<div class="user-entry">
				<h2 class="form-title">User Two</h2>
				<label>Name: </label><input type="text" name="user2-name"><br/>
				<label>XML File: </label><input type="file" name="user2-file">
			</div>

			<br/>
			<div class="form-submit"><input type="submit" value="Compare"></div>
		</form>
	</div>

	<div class="how-to-use">
		<h2>How To Use</h2>
		<p>Go to <a href="https://www.youtube.com/subscription_manager">https://www.youtube.com/subscription_manager</a>.</p>
		<p>Scroll to the bottom.</p>
		<p>Click "Export subscriptions".</p>
	</div>
	<?php
}

class subscription_counter {
	// $users[0] contains the shared channels.
	public $users = array();
	public $bothUsers = array();
	private $channelIds = array();


	private $apiKey = 'AIzaSyDPDJo3N_25-HhCfFBaZInTj0cjshuFnQA';


	public function runCounter() {
		for ($i = 1; $i < 3; $i++) {
			$this->assignChannels($i);
		}

		$this->addChannelStats();
		$this->removeEmptyChannels();
		$this->sortChannels();
		$this->titles = $this->assignTitles();

		echo "<div class='container'>";
		echo "<a href=''>&#8592; Back</a>";
		for ($i = 0; $i < count($this->users); $i++) {
			$this->populateHtml($this->titles[$i], $this->users[$i]);
		}
		echo "</div>";

		/*echo"<pre>";print_r($this->users[0]);echo"</pre><br>---------------------------------------------------------------------<br>";
		echo"<pre>";print_r($this->users[1]);echo"</pre><br>---------------------------------------------------------------------<br>";
		echo"<pre>";print_r($this->users[2]);echo"</pre><br>---------------------------------------------------------------------<br>";*/
		die;
	}

	private function assignTitles() {
		$userOne = empty($_POST['user1-name']) ? 'User One' : strip_tags($_POST['user1-name']);
		$userTwo = empty($_POST['user2-name']) ? 'User Two' : strip_tags($_POST['user2-name']);

		$titles = array(
			'Shared Subscriptions',
			$userOne . ' Only',
			$userTwo . ' Only'
		);
		
		return $titles;
	}

	private function getUserSubs($user) {
		return json_decode(json_encode(simplexml_load_file($_FILES[$user]['tmp_name'])))->body->outline->outline;
	}

	private function assignChannels($userNum) {
		$subs = $this->getUserSubs('user' . $userNum . '-file');

		foreach ($subs as $sub) {
			$sub = $sub->{'@attributes'};

			$channelId = substr(strrchr($sub->xmlUrl, '='), 1);
			$channelUrl = 'https://www.youtube.com/channel/' . $channelId;
			
			if ($userNum == 2 && !empty($this->users[1][$channelId])) {
				// Both users are subscribed.
				$this->users[0][$channelId] = $this->users[1][$channelId];
				unset($this->users[1][$channelId]);
			} else {
				$channel = array(
					'name' => $sub->title,
					'url' => $channelUrl
				);

				$this->users[$userNum][$channelId] = $channel;
				$this->channelIds[] = $channelId;
			}
		}	
	}

	private function addChannelStats() {
		$allStats = array();

		$i = $n = 0;
		foreach ($this->channelIds as $id) {
			if ($i == 0) {
				$query = "https://www.googleapis.com/youtube/v3/channels?part=statistics,snippet&key=" . $this->apiKey . "&id=";				
			} else {
				$query .= ',';
			}

			$query .= $id;

			$i++;
			$n++;

			// Limit of 50 channels per request.
			if ($i % 50 == 0 || $n == count($this->channelIds)) {
				$stats = json_decode(file_get_contents($query))->items;

				foreach ($stats as $s) {
					$allStats[$s->id] = $s;
				}

				$i = 0;
			}
		}

		foreach ($allStats as $id => $details) {
			for ($i = 0; $i < count($this->users); $i++) {
				if (array_key_exists($id, $this->users[$i])) {
					$stats = $details->statistics;
					$thumbnail = $details->snippet->thumbnails->default->url;

					$this->users[$i][$id]['viewCount'] = $stats->viewCount;
					$this->users[$i][$id]['commentCount'] = $stats->commentCount;
					$this->users[$i][$id]['subscriberCount'] = $stats->subscriberCount;
					$this->users[$i][$id]['videoCount'] = $stats->videoCount;
					$this->users[$i][$id]['thumbnail'] = $thumbnail;

					continue 2;
				}
			}	
		}
	}

	private function sortChannels($sortBy = 'subscriberCount', $sortOrder = 'desc') {
		$this->sortBy = $sortBy;
		$this->sortOrder = $sortOrder;

		for ($i = 0; $i < count($this->users); $i++) {
			usort($this->users[$i], function($a, $b) {
				if ($this->sortOrder == 'desc') {
					return $b[$this->sortBy] - $a[$this->sortBy];
				} else {
					return $a[$this->sortBy] - $b[$this->sortBy];
				}				
			});
		}
	}

	private function removeEmptyChannels() {
		for ($i = 0; $i < count($this->users); $i++) {
			foreach ($this->users[$i] as $id => $channel) {
				if (!isset($channel['subscriberCount'])) {
					unset($this->users[$i][$id]);
				}
			}
		}
	}

	private function populateHtml($sectionName, $subsArray) {
		?>
		<div class="results">
			<h2 class="section-title"><?php echo $sectionName; ?> (<?php echo count($subsArray); ?>)</h2>
			<ul>
				<?php
				foreach ($subsArray as $sub):
					?>
					<li>
						<img src="<?php echo $sub['thumbnail']; ?>" class="channel-thumbnail" />
						<a href="<?php echo $sub['url']; ?>" class="channel-link"><?php echo $sub['name']; ?> (<?php echo number_format($sub['subscriberCount']); ?>)</a>
					</li>
					<?php
				endforeach;
				?>
			</ul>
		</div>

		<?php
	}

}

?>