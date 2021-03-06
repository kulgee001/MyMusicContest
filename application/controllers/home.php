<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Home extends CI_Controller {

	function __construct() 
	{

		parent::__construct();

		//load helpers
		$this->load->helper('form'); //form helper
		$this->load->helper('url'); //url helper

		//load libraries
		$this->load->library('session'); //session library

		//load models
		$this->load->model('user_dl');
		
		$this->user_dl->create_table();

		$msg = null;

	}

	//default method executed when home controller is called
	public function index()
	{
		$this->load->view('homepage');
		$this->session->unset_userdata('uploadmsg');
	}

	//this function uploads the report file to a location on the server
	function do_upload() 
	{

		$config['allowed_types'] = 'csv';
		$config['max_size']	= '3072';
		$config['remove_spaces'] = TRUE;
		$config['encrypt_name'] = TRUE;
		$config['upload_path'] = "./uploadedfiles/";
		$field_name = 'csvfile';

		$this->load->library('upload', $config);

		if ( ! $this->upload->do_upload($field_name))
		{

			$msg = $this->upload->display_errors();
			$this->session->set_userdata('uploadmsg', $this->msg);
			redirect('home');

		}
		else
		{

			$file_data = $this->upload->data();
			$this->session->set_userdata('file_data', $file_data);
			$filename = $file_data['file_name'];
			$this->save_to_database($filename);
			redirect('home');
			
		}

	}

	//this function is used to parse the CSV file for saving to database
	private function parse_file($p_Filepath, $p_NamedFields = true) 
	{

		/*
			Allow you to retrieve a CSV file content as a two dimensional array.
			Optionally, the first text line may contain the column names to
			be used to retrieve field values (default).
			
			Let's consider the following CSV formatted data:
			
			  "col1";"col2";"col3"
			      "11";"12";"13"
			      "21;"22;"2;3"
			 
			 It's returned as follow by the parsing operation with first line
			 used to name fields:
			         Array(
			             [0] => Array(
			                     [col1] => 11,
			                     [col2] => 12,
			                    [col3] => 13
			             	     )
			             [1] => Array(
			                     [col1] => 21,
			                     [col2] => 22,
			                     [col3] => 2;3
			             	    )
			       	   )
			@author		Pierre-Jean Turpeau
			@link		http://www.codeigniter.com/wiki/CSVReader 
		*/

	    $separator = ',';
	    $max_row_size = 4096;

        $content = false;
        $file = fopen($p_Filepath, 'r');
        if($p_NamedFields) {
            $fields = fgetcsv($file, $max_row_size, $separator);
        }
        while( ($row = fgetcsv($file, $max_row_size, $separator)) != false ) {            
            if( $row[0] != null ) {
                if( !$content ) {
                    $content = array();
                }
                if( $p_NamedFields ) {
                    $items = array();

                    foreach( $fields as $id => $field ) {
                        if( isset($row[$id]) ) {
                            $items[$field] = $row[$id];    
                        }
                    }
                    $content[] = $items;
                } else {
                    $content[] = $row;
                }
            }
        }

        fclose($file);
        return $content;

    }

    //this function saves the data to database
    function save_to_database($filename)
    {

    	$filename = './uploadedfiles/'.$filename;
		$records = $this->parse_file($filename);

		$this->user_dl->drop_table();

		if (!empty($records)) {

			$this->user_dl->create_table();

			for ($i = 0; $i < count($records); $i++){

				$id = $records[$i]['ID'];
				$track_id = $records[$i]['TRACK ID'];
				$ip_address = $records[$i]['IP ADDRESS'];
				$expiry_date = date('Y-m-d H:i:s', strtotime($records[$i]['EXPIRY DATE']));
				$transaction_id = $records[$i]['TRANSACTION ID'];
				$dl_status = $records[$i]['STATUS'];
				$dl_source = $records[$i]['SOURCE'];
				$dl_type = $records[$i]['TYPE'];
				$dl_date = date('Y-m-d H:i:s', strtotime($records[$i]['DOWNLOAD DATE']));

				try {

					$this->user_dl->add($id, $track_id, $ip_address, $expiry_date, $transaction_id, $dl_status, $dl_source, $dl_type, $dl_date);

				} catch(Exception $e) {

					echo 'Error occurred: ', $e->getMessage(), "\n";

				}
				

			}

			$msg = 'Saving to database completed!';
			$this->session->set_userdata('uploadmsg', $msg);

		} else {

			$msg = 'No content to save to database!';
			$this->session->set_userdata('uploadmsg', $msg);

		}

    }

    //this function loads the view that displays the report in tabular form
    function load_table()
    {

    	try {
    		$records = $this->user_dl->getAll();
    	} catch (Exception $e) {
    		echo "Database Error: $e->getMessage()"; 
    	}
    	
		$data['records'] = $records;
		$this->load->view('report', $data);

    }

    //this function executes the search query by IP and loads the report view with the data 
    function search()
    {
    	$ip_address = trim($this->input->post('ipaddress'));

    	$records = $this->user_dl->getByIP($ip_address);

		$data['records'] = $records;
		$this->load->view('report', $data);
    }

    //this function executes the search query by dates and loads the report view with the data 
    function searchdates()
    {
    	$datefrom = trim($this->input->post('date1'));
    	$dateto = trim($this->input->post('date2'));
    	$selectdate = $this->input->post('selectdate');

    	$date = ($selectdate == 1 ? 'expiry_date' : 'dl_date');

    	$records = $this->user_dl->getByDate($date, $datefrom, $dateto);

		$data['records'] = $records;
		$this->load->view('report', $data);
    }
}
