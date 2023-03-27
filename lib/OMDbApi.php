<?php

class OMDbApi
{
    protected $api_key;

    const API_URL = 'https://www.omdbapi.com/';

    public function __construct($api_key)
    {
        // TODO - validate api key before we assign to the property
        $this->api_key = $api_key;
    }

    /**
     * lookupByTitle
     *
     * Lookup a movie by title
     *
     * @param   $title  Movie title to search for
     * @return  $OMDbResult|null
     *      $OMDbResult = {
     *          imdbID  =>  (string) IMDb ID
     *          Title   =>  (string) Title of the film
     *          Year    =>  (string) Release Year
     *          Type    =>  (string) movie, series, or episode
     *          Poster  =>  (string) URI of the movie poster
     *      }
     */
    public function lookupByTitle($title)
    {
        $url = sprintf('%s/?apikey=%s&t=%s', OMDbApi::API_URL, $this->api_key, urlencode($title));

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_URL, $url);

        $result = json_decode(curl_exec($ch));

        if ($result->Response === "False") {
            return null;
        }

        return $result;
    }
}
