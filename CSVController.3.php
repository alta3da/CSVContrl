<?php

require_once($_SERVER['DOCUMENT_ROOT'].'/models/CSVModel.php');



if(isset($_GET['slug'])){

    $csv = new CSVCLass;

    $csv->getCityData($_GET['slug']);

}


Class CSVClass extends CSVModel{

    private $_file = array();

    private $_db_data = array();

    private $_new_file_data = array();

    private $_old_file_data = array();

    private $_fields= array();

    private $_addr_attentions = array();

    private $_point_attentions = array();

    private $_phone_attentions= array();

    private $_opt = array();

    private $_db;

    function __construct(){  
        
        $this->setOptsFromGET();

        $this->readCSVfile();

        $this->_db = new CSVModel; 
       
    }

    public function getOpts(){

        return $this->_opt;

    }

    public function processCSVFile(){

        /**
         * Set Pattern checks into $checks_array::
         * 
         * Address-city check pattern: 'addr';
         * 
         * Points check pattern: 'point';
         * 
         * Points check pattern: 'phone_site';
         */

        $checks_array = ['addr', 'point', 'phone_site'];
        

        if(isset($this->_opt['save']) && $this->_opt['save'] == 2){

            echo '<br><br>Saving to DataBase <b>without Deleting</b> repeating Data...<br><br>';
        
            return;
        
        } 
        
        foreach($checks_array as $check_pattern){

            $coinsidence_patterns[$check_pattern] = $this->findDataRepeats($this->_file, $check_pattern);
        }       
                      
                
        if(isset($this->_opt['show_reps']) && $this->_opt['show_reps'] == 1){

            foreach($checks_array as $check_pattern){

                $coinsidence_patterns[$check_pattern] = $this->printAttentions($check_pattern);
            }  
        
        }
        
        if(isset($this->_opt['save']) && $this->_opt['save'] == 1){

            foreach($checks_array as $check_pattern){

                $this->deleteNonUniqueData($coinsidence_patterns[$check_pattern], $check_pattern);

                $this->_file = $this->rearrangeArray($this->_file);  
            } 
            

            /* Get DB Data and count DB Data */

            $this->_db_data = $this->_db->getDBData();
            
            $db_rows_count = count($this->_db_data);

            echo '<br><br><span class="info-info">Data statistics</span>: DB contains: '.$db_rows_count.' rows; ';

            $csv_rows_count = count($this->_file);

            echo 'CSV data contains '.$csv_rows_count.' rows'; 


            /* If Db Data contains MORE rows that CSV Data - stop file proceeding */
            if($db_rows_count > $csv_rows_count){

                echo '<br><br><span class="danger-info">CSV file contains LESS ROWS than DB Data. File proceeding ABORTED! 
                <br>Please, check your CSV file</span>';

                echo '<br><br><a href="/data_checker.php" class="btn btn-primary"> Back to Start </a>';

                return;
            }


            $new_CSV_data_count = $csv_rows_count - $db_rows_count;

            echo '<br><br><span class="info-info">New CSV data contains:</span> '.$new_CSV_data_count.' rows'; 

            $csv_start_row = $csv_rows_count - $new_CSV_data_count;

            $csv_end_row = $csv_rows_count - 1; 
            
            $this->separateCSVData($csv_start_row);            

            echo '<br><br>New data List: ';

            $this->printData($this->_new_file_data,['mode'=>'show']);           
            

               
          

            /* Compare Old CSV Data with DB Data (always Old) */

            $nonidents_array = $this->checkArraysOnIdentity($this->_old_file_data, $this->_db_data); 

            echo '<br><br>Old data List: ';            
            
             
            /* If Old CSV Data and DB Data are identical -> try to flush New CSV Data to DB */

            if(empty($nonidents_array)){ 
               
                /* Check if CSV file has New Data */    

                if($this->_new_file_data){

                    /* Flush New CSV Data into DB */
                
                    if($rows_count = $this->_db->CSVtoDB($this->_new_file_data)){

                        echo '<br><br><span class="success-info">Successfully added to DB: '.$rows_count.' rows</span>';

                    }

                }

                else{

                    echo '<br><br><span class="info-info">CSV file has NO exceeding Data. Nothing to add to DB</span>';

                    $this->printData($this->_old_file_data,['mode'=>'update', 'data'=>$nonidents_array]);
                }

                
                /*  Consistency check: 
                *   CSV file should be absolutely identical to DB content 
                */

                $this->runConsistencyCheck();
                
            }

            else{                
                
                /* Perform Update (flush DB)*/
                if($this->flushUpdateRequirements()){

                    $this->flushUpdate();
                }

                /* Show preUpdate Dialog (no Update process performed)*/
                if($this->preUpdateRequirements()){

                    $this->preUpdateDialog();                 

                } 

                $nonidents_array = $this->checkArraysOnIdentity($this->_file, $this->_db_data);

                $this->printData($this->_old_file_data,['mode'=>'update', 'data'=>$nonidents_array]);                
                
            }           

        }
        
    }

    private function preUpdateRequirements(){

        if(isset($this->_opt['save']) && $this->_opt['save'] == 1 && isset($this->_opt['show_reps']) && $this->_opt['show_reps']==0 && isset($this->_opt['update_id'])){

            return true;
        }

        return false;
    }

    private function preUpdateDialog(){

        echo '<span class="info-info">Updating DB row: '.$this->_opt['update_id'].'</span>';

        $this->_db_data = $this->_db->getDBData($this->_opt['update_id']);            

        echo '<br><span class="warning-info">Ready to update DB row: </span>';
        $this->printDataRow($this->_db_data[$this->_opt['update_id']]);                   
        
        echo '<br><span class="warning-info">to CSV row</span>: '.$this->_opt['update_id'];

        $this->printDataRow($this->_old_file_data[$this->_opt['update_id']]);                   

        echo '<form method="post" action="/data_checker.php?save=1&update=2&csv_id='.$this->_opt['update_id'].'&db_id='.$this->_db_data[$this->_opt['update_id']]['id'].'">
        <input type="submit" value="Perform Update DB"/>
        </form>';

        return;
    }

    private function runConsistencyCheck(){

        $this->_db_data = $this->_db->getDBData();
                        
        $final_check_array = $this->checkArraysOnIdentity($this->_file, $this->_db_data); 

        if(empty($final_check__array)){

            echo '<br><br><span class="success-info">Final check:CSV Data is absolutely identical to DB</span>';
        }

        else{

            echo '<br><br><span class="danger-info">CSV Data is NOT identical to DB Data. Please, check your Data</span>';
        }

        echo '<br><br><a href="/data_checker.php" class="btn btn-primary"> Back to Start </a>';
    }

    private function flushUpdateRequirements(){

        if(isset($this->_opt['update']) && $this->_opt['update']==2 && isset($this->_opt['csv_id']) && isset($this->_opt['db_id'])){

            return true;
        }

        return false;
    }

    private function flushUpdate(){

        $params = $this->_old_file_data[$this->_opt['csv_id']];

        $rows_count = $this->_db->updateDBRow('`points`',$params, $this->_opt['db_id']);

        if(!empty($rows_count)){

            echo 'DB row:'.$rows_count.' updated successfully';           
            
        }

        else{

            echo '<span class="warning-info">No rows affected. Check your Data or DB Connection</span>';
        }  
        
        echo '<br><br><a href="/data_checker.php?save=1&show_reps=0" class="btn btn-primary"> Back </a>';

        return;
    }

    private function printData(array $data, array $params){
       

        if($data){


            if($params['mode'] == 'update'){            
            
                foreach($data as $row_key=>$row_values){
                    
                    $data[$row_key]['Update'] = '<span class="success-info">No change</span>';                    

                    if(isset($params['data'][$row_key]) && !empty($params['data'][$row_key])){                        
    
                        $data[$row_key]['Update'] = '<form method="post" action="/data_checker.php?save=1&show_reps=0&update_id='.$row_key.'"><input type="submit" value="Update DB"/></form>';
                        
                    }
                }

            }


            echo '<table class="print_data_table">'; 

            /* Retrieving Table Head */
            echo '<tr>';
            foreach($data[0] as $key=>$value){             

                echo '<td>'.$key.'</td>';                
                
            }          
            echo '</tr>';


             /* Retrieving Table Body */
            foreach($data as $key=>$row){

                echo '<tr>';               
                                
                foreach($row as $value){

                echo '<td>'.$value.'</td>';            
                               
                }

                

                echo '</tr>';
            }          
            
            echo '</table>';

        }
        
        else{

            echo '<span class="success-info">No data to print</span>';
        }
        
    }

    private function printDataRow(array $data){
       

        if($data){



            echo '<table class="print_data_table">'; 

            /* Retrieving Table Head */
            echo '<tr>';
            foreach($data as $key=>$value){             

                echo '<td>'.$key.'</td>';                
                
            }          
            echo '</tr>';


             /* Retrieving Table Body */

             echo '<tr>';

            foreach($data as $row){

                echo '<td>'.$row.'</td>';          
                 
            } 
            
            echo '</tr>';
            
            echo '</table>';

        }
        
        else{

            echo '<span class="success-info">No data to print</span>';
        }
        
    }

    private function checkArraysOnIdentity(array $test_array, array $etalon){ 

        $nonidents_array = array();
        
        for($row_count = 0; $row_count < count($etalon); $row_count++){

            $test_ar_pattern = $this->getIdentityPattern($test_array[$row_count]);

            $etalon_pattern = $this->getIdentityPattern($etalon[$row_count]);

            if($test_ar_pattern != $etalon_pattern){
            
                $nonidents_array[$row_count] = $test_array[$row_count];
            }
        }

        return $nonidents_array;
      
    }

    private function getIdentityPattern(array $data){

        $pattern = '';

        foreach($data as $key=>$value){

            if($key != 'id'){

                $pattern .= $data[$key];

            }
        }

        return $pattern;
    }

    private function separateCSVData(int $csv_start_row){

        $row_num = 0;

        foreach($this->_file as $row){

            if($row_num >= $csv_start_row){

                $this->_new_file_data[] = $row;
            }

            else{

                $this->_old_file_data[] = $row;
            }

            $row_num ++;
        }
    }

    private function setOptsFromGET(){

        /* Set default Options */

        $this->_opt['save'] = 0;

        $this->_opt['show_reps'] = 1;

        /* Replace default Options with GET params */

        foreach($_GET as $key=>$value){

            $this->_opt[$key] = $value;
        }  

    }

    private function readCSVfile(){

        $row = 0;

        if (($handle = fopen($_SERVER['DOCUMENT_ROOT']."/data/test.csv", "r")) !== FALSE) {
            
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {                
                
                $row++;

                for ($i=0; $i < count($data); $i++) {

                    if($row == 1){

                        $this->_fields[$i] = $data[$i];                        
                    } 
                    
                    else{

                        $this->_file[$row-2][$this->_fields[$i]] = $data[$i];
                    }
                    
                }
            }
            fclose($handle);

        } 

    }

    public function getCityData($slug){

        $response = array();

        foreach($this->_file as $row){

            if($row['city'] == $slug){

                $response[] = $row;

            }
        }

        echo json_encode($response);
    } 

    private function printAttentions($pattern){        
        
        if($count_patterns = $this->getCountPatterns($pattern)){

            echo '<br><br><span class="warning-info">ATTENTION!</span>';  

            foreach($count_patterns as $row){

                echo '<br/><span class="warning-info">'.$row['count'].' Repeating patterns ('.$row['pattern'].') found at CSV rows: '.$row['rows'].' (Rows '.$this->getCSVRows($row['rows']).' in CSV file)</span>';
                echo '<br/><span class="warning-info">Must be deleted '.($row['count']-1).' row(s): '.substr($row['rows'],2, strlen($row['rows'])).'</span>';
    
            }

        }

        else{

            echo '<br/><span class="success-info"> No City-Address pattern repeats found</span>';

        }          
               
    }

    private function getCSVRows($data){

       $data = explode(',', $data);

        foreach($data as $key=>$value){
            
            $data[$key] = $value + 2;
        }

        return implode(",", $data);


    }

   
    private function checkPatternExists($comp_arr, $value){

        foreach($comp_arr as $row){

            if($row['pattern'] == $value){               

                return true;
            }
        }

        return false;

    }

    private function getCountPatterns(string $pattern){

        $count_patterns=array();

        $count_patterns_row = 0;

        $hasValue = 0;

        if($pattern == 'addr'){

            $source_array = $this->_addr_attentions;
        }

        if($pattern == 'point'){

            $source_array = $this->_point_attentions;
        }

        if($pattern == 'phone_site'){

            $source_array = $this->_phone_attentions;            
        }
        

        foreach($source_array as $key=>$value){           
            

            if(!count($count_patterns)){               

                $count_patterns[$count_patterns_row]['pattern'] = $value;

                $count_patterns[$count_patterns_row]['count'] = "";

                $count_patterns[$count_patterns_row]['rows'] = "";

                $count_patterns_row ++;

            }
            
            foreach($count_patterns as $pat_key=>$pat_value){                
                
                if($count_patterns[$pat_key]['pattern'] != $value){                    

                    if(!$this->checkPatternExists($count_patterns,$value)){

                       
                        $count_patterns[$count_patterns_row]['pattern'] = $value;

                        $count_patterns[$count_patterns_row]['count'] = 1;

                        $count_patterns[$count_patterns_row]['rows'] = $key;

                        $count_patterns_row ++;

                    }
                                        
                } 

                else{                    

                    $count_patterns[$pat_key]['count'] ++;

                    if(!$count_patterns[$pat_key]['rows']){

                        $count_patterns[$pat_key]['rows'] .= $key;
                    }

                    else{

                        $count_patterns[$pat_key]['rows'] .= ','.$key;
                    }

                }
        }
          
        }  
       
        return $count_patterns;
    }

    private function findDataRepeats($data, $pattern){        

        $coinsidence_patterns = array();        

        $row_count = 0;       

        foreach($data as $row){

            $compared_pattern = $this->getComparedPattern($row, $pattern);            
            
            $coinsidence_count = 0;

            foreach ($this->_file as $file_item){              

                $item_address = $this->getComparedPattern($file_item, $pattern);

                if($compared_pattern == $item_address){

                    $coinsidence_count++;

                    if($coinsidence_count == 2){                        
                        
                        if($pattern == 'addr'){

                            $this->_addr_attentions[$row_count] = $compared_pattern;         

                        } 
                        
                        if($pattern == 'point'){

                            $this->_point_attentions[$row_count] = $compared_pattern;         

                        }

                        if($pattern == 'phone_site'){

                            $this->_phone_attentions[$row_count] = $compared_pattern;         

                        }
                             
                       
                        $coinsidence_patterns = $this->optimizeNonUniqueArray($coinsidence_patterns, $compared_pattern);    
                    }
                    
                }                
                
            }

            $row_count++; 
        }
 

        return $coinsidence_patterns;

    }

    private function optimizeNonUniqueArray($coinsidence_patterns, $compared_address){

        $coinsidence_patterns[] = $compared_address;

        $c_count = 0;

        foreach($coinsidence_patterns as $key=>$value){

            if($value == $compared_address){                

                $c_count++;

                if($c_count > 1){

                    unset($coinsidence_patterns[$key]);

                }

            }
        } 

        return $coinsidence_patterns;

    }

    private function deleteNonUniqueData($coinsidence_patterns, $c_pattern){  
        
        $repeats_array = $this->createCoinsPatternsArray($coinsidence_patterns);       
 

        $row_count = 0;

        $rows_deleted = 0;

        $rows_log = "";
      
        foreach ($this->_file as $key=>$value){            

            $compared_addr = $this->getComparedPattern($value,$c_pattern);           
           
            foreach($coinsidence_patterns as $pattern){                

                // echo '<br>Compared with :'.$pattern;

                if($compared_addr == $pattern){                    

                    $repeats_array[$pattern]++;                 

                    if($repeats_array[$pattern] > 1){

                        echo '<br><span class="warning-info">Deleted pattern:</span> '.$compared_addr.' : at row: '.$row_count.'<br>';

                        unset($this->_file[$key]);

                        $rows_deleted++;

                        $rows_log .= $row_count.' ,';
                    }
                    
                }                
                
            } 
            
            $row_count++;

        } 

        echo '<pre>';

        print_r($this->_file);
        
        echo'</pre>';
        
        echo '<br><span class="warning-info"> Deleted (pattern: '.$c_pattern.') '.$rows_deleted. ' rows: '.substr($rows_log,0,strlen($rows_log)-1).'</span>';
     
    }

    private function getComparedPattern($data, $pattern){

        if($pattern == 'addr'){

            return $data['city'].$data['street_type'].$data['street'].$data['house'].$data['apartment'];

        }

        if($pattern == 'point'){

            return $data['lng'].$data['lat'];

        }

        if($pattern == 'phone_site'){

            return $data['phone'].$data['site'];

        }

        
    }

    private function createCoinsPatternsArray($coinsidence_patterns){

        $repeats_array = array();

        foreach($coinsidence_patterns as $pattern){

            $repeats_array[$pattern] = 0;
        }

        return $repeats_array;

    }

    private function rearrangeArray($orig_array){

        $rearranged_array = array();

        foreach($orig_array as $item){

            $rearranged_array[] = $item;
        }

        return $rearranged_array;
    }
}