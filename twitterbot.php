<?php

require_once('twitter-api-php.php'); //SOURCE: https://github.com/J7mbo/twitter-api-php

//THESE TOKENS COME FROM THE TWITTER API DEVELOPER CONSOLE AND HAVE BEEN REDACTED. I CREATED A NEW ADDRESS TO TEST WITH SO AS NOT TO CLUTTER MY TIMELINE.
$settings = array(
'oauth_access_token' => 'XXXXXXXXXXXXXXX',
'oauth_access_token_secret' => 'XXXXXXXXXXXXX',
'consumer_key' => 'XXXXXXXXXXXX',
'consumer_secret' => 'XXXXXXXXXXXXXXX'
);


/*bearer token : XXXXXXXXXXXJ*///NOT SURE WHAT THIS DOES

// SEARCH FOR NEW TWEETS USING HASHTAG
$url    = 'https://api.twitter.com/1.1/search/tweets.json';
$method = 'GET';
$params = '?q=@testplacesearch';
$twitter = new TwitterAPIExchange($settings);
$response = $twitter->setGetfield($params)->buildOauth($url, $method)->performRequest();
//echo $response;

// COLLECT DATA REQUIRED FOR RESPONSE
$response = json_decode($response, true);
$place = explode("@testplacesearch", $response["statuses"][0]["text"])[1];
$place = trim($place);
$id = $response["statuses"][0]["id"];
$time = $response["statuses"][0]["created_at"];
$screen_name = $response["statuses"][0]["user"]["screen_name"];

//FETCH COORDINATES FROM GOOGLE MAPS
$url = "https://maps.googleapis.com/maps/api/geocode/json?address=".$place.",UK&key=AIzaSyCFNgMdG__MH5xwCSeMuA1xSrae-urczmg";
$url = preg_replace('/\s+/', '', $url);
$ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    if(curl_exec($ch) === FALSE) {
         echo "Error: " . curl_error($ch);
    } else {
        $coords = curl_exec($ch);
    }
    curl_close($ch);
$location_data = json_decode($coords, true);
$lat = $location_data["results"][0]["geometry"]["location"]["lat"];
$lng = $location_data["results"][0]["geometry"]["location"]["lng"];

file_put_contents($filename, trim($commentnumber).PHP_EOL, FILE_APPEND);

//HERE'S WHAT WE'VE GOT SO FAR
echo 'Tweet id:     '.$id;
echo "<br>";
echo 'Tweet time:   '.$time;
echo "<br>";
echo 'Tweet place:  '.$place;
echo "<br>";
echo 'url:  '.$url;
echo "<br>";
echo "lat: ". $lat;
echo "<br>";
echo "lng: ". $lng;
echo "<br>";

//FIND THE LSOA
$url = "https://museum.placeandpurpose.co.uk/geoJSON_museum.php"; //QUERYING MY OWN DATABASE
$fields = [
    'lat' => $lat,
    'lng' => $lng
];
$fields_string = http_build_query($fields);
$ch = curl_init();
curl_setopt($ch,CURLOPT_URL, $url);
curl_setopt($ch,CURLOPT_POST, true);
curl_setopt($ch,CURLOPT_POSTFIELDS, $fields_string);
curl_setopt($ch,CURLOPT_RETURNTRANSFER, true); 
$location = curl_exec($ch);
//print_r($location);
$location = json_decode($location, true);
$location = $location["features"];
$lsoas = Array();

foreach ($location as $lsoa){
array_push( $lsoas, $lsoa["properties"]["lsoa_code"]);
}
echo "30 lsoas around the centre of $place :";
echo json_encode($lsoas);

//GET SOME CONTENT - IN THIS CASE, SERVER GENERATED HTML POPULATION PYRAMIDS


$fields = [
    'lsoas' => $lsoas,
    'place' => $place
];
$fields_string = http_build_query($fields);
$url = "https://museum.placeandpurpose.co.uk/getDemographics_twitter.php?place=".$place;
$ch = curl_init();
curl_setopt($ch,CURLOPT_URL, $url);
curl_setopt($ch,CURLOPT_POST, true);
curl_setopt($ch,CURLOPT_POSTFIELDS, $lsoas);
curl_setopt($ch,CURLOPT_RETURNTRANSFER, true); 
$content = curl_exec($ch);
echo $content;

//CONVERT THE HTML TO AN IMAGE USING htmlcsstoimage.com

$html = $content;
$google_fonts = "Roboto";
$data = array('html'=>$html,
              'google_fonts'=>$google_fonts);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://hcti.io/v1/image");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_USERPWD, "90eaac19-cf95-4c0e-aae0-d36a95ccc740" . ":" . "df1e6092-04a7-4429-860e-27471b0a4736");// Retrieve your user_id and api_key from https://htmlcsstoimage.com/dashboard

$headers = array();
$headers[] = "Content-Type: application/x-www-form-urlencoded";
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

$result = curl_exec($ch);
if (curl_errno($ch)) {
  echo 'Error:' . curl_error($ch);
}
curl_close ($ch);
$res = json_decode($result,true);
$image_url = $res['url']; //<- THIS IS THE SCREENSHOT OF THE OUTPUT
//$file = file_get_contents($image_url);
//$image = base64_encode($file);

$input = $image_url;
$output =  $place . '.png';
file_put_contents($output, file_get_contents($input));
$new_image_location = 'https://museum.placeandpurpose.co.uk/twitterplace/' . $output;

//TWEET THE RESPONSE TO THE ORIGINAL SENDER

$twitter = new TwitterAPIExchange($settings); //REFRESH THE Twitter object because it can'yt mix GET and POST
$url = 'https://upload.twitter.com/1.1/media/upload.json';
$requestMethod = 'POST';

$image = $new_image_location;

$postfields = array(
  'media_data' => base64_encode(file_get_contents($image))
);

$response = $twitter->buildOauth($url, $requestMethod)
  ->setPostfields($postfields)
  ->performRequest();

// get the media_id from the API return
$media_id = json_decode($response)->media_id;

// then send the Tweet along with the media ID
$url = 'https://api.twitter.com/1.1/statuses/update.json';
$requestMethod = 'POST';

$status_txt = "Hey @" . $screen_name . ", here's the population of " . $place;

$postfields = Array(
'status' => $status_txt,
'media_ids' => $media_id,
'in_reply_to_status_id' => $id
);

$response = $twitter->buildOauth($url, $requestMethod)
  ->setPostfields($postfields)
  ->performRequest();


?>
