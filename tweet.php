<?php
require "vendor/autoload.php";
use Abraham\TwitterOAuth\TwitterOAuth;

date_default_timezone_set('Europe/London');

//Get the consumer (app's) key & secret from: https://apps.twitter.com/app/8786076/keys
define('CONSUMER_KEY', getenv("TWITTER_CONSUMER_KEY"));
define('CONSUMER_SECRET', getenv("TWITTER_CONSUMER_SECRET"));
define('ACCESS_TOKEN',  getenv("TWITTER_ACCESS_TOKEN"));
define('ACCESS_TOKEN_SECRET',  getenv("TWITTER_ACCESS_TOKEN_SECRET"));

if(!CONSUMER_KEY || !CONSUMER_SECRET || !ACCESS_TOKEN || !ACCESS_TOKEN_SECRET ){
    die("Twitter access tokens etc. must be provided through ENV vars.");
}

//Get the access token linked to the user (for the app above) from the Access token section of https://apps.twitter.com/app/8786076/keys
// It's easy to use that page to get an access token for the app user- oAuth1 sucks, requires a browser, a redirect URL to a live app, etc.
$connection = new TwitterOAuth(CONSUMER_KEY, CONSUMER_SECRET, ACCESS_TOKEN, ACCESS_TOKEN_SECRET);

//Get current list of car parks occupancy from Socrata.
$json = file_get_contents('https://data.bathhacked.org/resource/u3w2-9yme.json');
$carparkdata = json_decode($json, true);

//Split the car park data into lists of full & empty.
$fullCarParks = [];
$emptyCarParks = [];
forEach( $carparkdata as $carpark){
  $lastUpdate = strtotime($carpark['lastupdate']);
  $spaces = max( min( $carpark['capacity'], $carpark['capacity'] - $carpark['occupancy'], 1100), 0);
  $name = $carpark['name'];
  $name = str_replace('P+R', 'Park & Ride', $name);
  $name = str_replace('CP', 'Car Park', $name);

  if( $lastUpdate > time()-30*60 && $spaces < 15 ){
    $fullCarParks[] = Array('spaces' => $spaces, 'name' => $name);
  }
  elseif( $spaces > 100 ){
    $emptyCarParks[] = Array('spaces' => $spaces, 'name' => $name);
  }
}

if( count($fullCarParks) > 0 ){
  //Get the most recent tweets. If we tweeted about this car park in the last hour, don't do so again.
  //Parse the recent tweets and pull out the names of recently tweeted about CPs to avoid needing any storage / DB.
  $recentlyTweetedFull = [];
  $recentTweets = $connection->get('statuses/user_timeline');
  forEach( $recentTweets as $recentTweet){
    if( strtotime($recentTweet->created_at) > time()-60*60 && strpos($recentTweet->text, "is FULL") !== false){
      $recentlyTweetedFull[] = trim(str_replace('&amp;', '&', substr($recentTweet->text, 0, strpos($recentTweet->text, " is FULL"))));
    }
  }

  forEach( $fullCarParks as $carpark){
    //Check if this car park was mentioned recently.
    if( in_array($carpark['name'], $recentlyTweetedFull) ){
      echo $carpark['name']." is full, but we tweeted about it less than an hour ago.\n";
      continue;
    }

    //Create the tweet text & post it.
    $status = $carpark['name']." is FULL";
    if( count($emptyCarParks) > 0){
      $status .= ", but ".count($emptyCarParks)." car parks still have plenty of space: http://www.bathcarparks.co.uk/";
    }
    else{
      $status .= ".";
    }

    $tweetDetails = array(
      "status" => $status, 
      "lat" => 51.381521,
      "long" => -2.360389,
      "place_id" => "1db4f0a70fc5c9db" //Bath
    );
    echo "Tweeting: ".$tweetDetails['status']."\n";
    $response = $connection->post("statuses/update", $tweetDetails);
  }
}
else{
  echo "No car parks are full. Not tweeting. \n";
}
?>
