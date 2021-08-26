# twitterbot_proof_of_concept

A PHP script that uses:

    1. The Twitter API to search for keyword "@testplacesearch" followed by the name of a place (eg. "Little Grimsby")
    
    2. Google Maps API to retreive the coordinates of the centre of the place
    
    3. cURL to request HTML content in response to the coordinates (this currently uses a placeandpurpose.co.uk script, but could be adapted to an ONS product)
    
    4. htmlcsstoimage.com to convert the html content to an image rendered in Chrome
    
    5. A new instance of the Twitter API to send the image back as a response to the original Tweeter
    
