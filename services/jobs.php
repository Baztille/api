<?php
/*********************************************************************************

    Baztille
        
    Copyright (C) 2015  Grégory Isabelli, Thibaut Villemont and contributors.

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
    
***********************************************************************************/

use Silex\ServiceProviderInterface;
use Silex\Application;

namespace baztille;

class jobs
{
	
    public function __construct($app)
    {
		$this->app = $app;
    }

    public function selectHottestQuestion()
    {
        return $this->app['questionFlow']->selectHottestQuestion();
    }


    public function closeVote()
    {
        return $this->app['questionFlow']->closeVote();
    }

    public function testjob()
    {
        return $this->app['questionFlow']->testjob();
    }

    public function refreshNews()
    {
        $ctx = stream_context_create(array('http'=>
            array(
                'timeout' => 10   // 5 seconds
            )
        ));

        $res = file_get_contents('https://medium.com/@baztille/latest?format=json', false, $ctx);

        if( $res === false )
        {
            throw new \Exception( "Error during news retrieval." );
        }
        else
        {
            $clean_res = strstr($res, '{');   

            // Store new list
            file_put_contents( '/var/baztille-data/news/news.json', $clean_res );
            
            $news = json_decode( $clean_res, true );
            $news = $news['payload']['posts'];
            
            // Then, get news one by one
            foreach( $news as $onenews )
            {
                $id = $onenews['id'];
                
                $url = 'https://medium.com/@baztille/'.urlencode($id).'?format=json';

                $res = file_get_contents( $url, false, $ctx);

                if( $res === false )
                {
                    throw new \Exception( "Error during news retrieval." );
                }
                
                $clean_res = strstr($res, '{');   

                // Store new list
                file_put_contents( '/var/baztille-data/news/news_'.$id.'.json', $clean_res );
            }    
        }


        return 'ok';
    }    

}
