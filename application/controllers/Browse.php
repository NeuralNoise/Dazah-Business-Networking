<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Browse extends CI_Controller
{    
    private function js_next_profile($offset)
    {
        // Restart at the beginning
        if ($offset >= 99)
        {
            $offset = -1;
        }
                
        // URL for the next profile
        $url = site_url('browse/profile/' . ++$offset);
        
        // Retrieve the entire profile
        ob_start();
        $this->profile($offset);
        $html = ob_get_clean();
        
        // Spit out JSON
        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode(array(
                'url' => $url,
                'html' => $html
            )));     
    }
    

    public function js_meet_profile()
    {
        if ($this->input->is_ajax_request())
        {
            $user_id = $this->input->post('id');
    
            // Make sure we are allowed to start a conversation with this user
    
            $match = api_endpoint("users/$user_id/match")[0];
                
            // They are free to meet
            if (isset($match->meet->price_usd) AND $match->meet->price_usd == 0)
            {
                // Start a new conversation with the user passed in via POST request                
                $result = api_endpoint("users/$user_id/meet", array(), true);
                
                if (isset($result->conversation->id))
                {        
                    $conversation_id = $result->conversation->id;
        
                    // Redirect to the conversation
                    $this->output
                        ->set_content_type('application/json')
                        ->set_output(json_encode(array('url' => conversation_url($conversation_id))));
                }
            }
        }
    }
    
	public function js_meet()
	{	
	    if ($this->input->is_ajax_request())
	    {
	        // Retrieve the ID
	        $user_id = $this->input->post('id');
	         
	        // Determine our relationship with them
	        $match = api_endpoint("users/$user_id/match")[0];
	         
	        $offset = abs(intval($this->input->post('offset')));
	         	        
	        // They are free to meet
	        if (isset($match->meet->price_usd) AND $match->meet->price_usd == 0)
	        {	            
	            // Start a new conversation with the user passed in via POST request
	            api_endpoint("users/$user_id/meet", array(), true);	             
	        }
	        
	        // JSON object increases the page offset
	        $this->js_next_profile($offset);
	    }	    
	}
	
	public function js_block()
	{	
		if ($this->input->is_ajax_request())
		{
			$offset = (intval($this->input->post('offset')));
			
			$user_id = $this->input->post('id');
			
			// Block the user passed in via POST request
			api_endpoint("users/$user_id/mute", array(), true);
				
			// JSON object increases the page offset
			$this->js_next_profile($offset);
		}
	}

	public function js_skip()
	{		
		if ($this->input->is_ajax_request())
		{	
			$offset = abs(intval($this->input->post('offset')));
			
			$user_id = $this->input->post('id');
			
			// Skip the user passed in via POST request
			api_endpoint("users/$user_id/skip", array(), true);
				
			// JSON object increases the page offset
			$this->js_next_profile($offset);
		}
	}
	
	public function index($hash = '')
	{
	    $this->profile();
	}
	
	public function profile($offset = 0)
	{		    	    	    
		$offset = abs(intval($offset));
		
		// Restart at the beginning
		if ($offset >= 100)
		{
		    $offset = 0;
		}
		
		// We send the offset to the template
		$this->wb_template->assign('offset', $offset);
		
		// Determine which profile to show next
				
		$request = array(
		    'url' => 'users/matches',
		    'params' => array(
		        'limit' => 1,
		        'offset' => $offset
		    )
		);
		
		$user = page_request($request, true);
			
		// The API endpoint is returning an array with only one user in it, since we have limit => 1
		if (isset($user[0]->id))
		{
		    // Determine our relationship with them
		    $match = api_endpoint("users/{$user[0]->id}/match")[0];

		    // Profiles are available (this used to be only if they were free, but now we allow payments)
		    if (isset($match->meet->price_usd) AND $match->meet->price_usd >= 0)
		    {	    
		        $user = json_decode(json_encode(array_merge_recursive((array)$user[0], (array)$match)));
		        
    			$this->wb_template->assign('user', $user);
    			
    			// Generate their profile
    			$this->wb_template->assign('profile_fragment', $this->load->view('app/profile/fragment', $this->wb_template->get(), true), true);
    		
    			// Spit out the entire page
    			$this->load->view('app/browse/profile', $this->wb_template->get());
		    }
		    else
		    {
		        // They are not free to meet because we have hit our maximum quota for the day
		        $this->load->view('app/browse/quota', $this->wb_template->get());
		    }
		}
		else if ($offset > 0)
		{
		    // Unavailable offset; Start at the beginning (recursive)
		    $this->profile();
		}
		else
		{
			// Error if no more profiles available to show
			$this->load->view('app/browse/oops', $this->wb_template->get());
		}
	}	
}