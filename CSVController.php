<?php

require_once($_SERVER['DOCUMENT_ROOT'].'/models/CSVModel.php');



if(isset($_GET['slug'])){

    $csv = new CSVCLass;

    $csv->getCityData($_GET['slug']);

}


Class CSVClass extends CSVModel{

    private $_file = array();

    private $_db_table = '`points`';

    private $_db_data = array();

    private $_new_file_data = array();

    private $_old_file_data = array();

    private $_fields= array();

    private $_attentions = array();

    private $_checks_array = array();

    private $_check_patterns = array();


    private $_opt = array();

    private $_db;

    function __construct(){ 
        
        /**
         * Set Pattern checks into $checks_array::
         * 
         * Address-city check pattern: 'addr';
         * 
         * Points check pattern: 'point';
         * 
         * Points check pattern: 'phone';
         */

        $this->_checks_array = ['addr', 'point', 'phone_site'];

        $this->_check_patterns= [

            'addr'=>['city','street_type','street','house','apartment'],

            'point'=>['lng','lat'],

            'phone_site'=>['phone','site']
        ];

        /**
         * Set $_GET Data to $this->_opt
         */
        
        $this->setOptsFromGET();

        /**
         * Load CSV file Data into $this->_file
         */

        $this->readCSVfile();

        /**
         * Initialize DB Class
         */

        $this->_db = new CSVModel; 
       
    }

    public function getOpts(){

        return $this->_opt;

    }

    public function processCSVFile(){   
        
        echo '<span class="info-info"><h3>Prosessing CSV file to DB table</h3></span>';
        
        /**
         * Save to DB without Checks
         */

        if(isset($this->_opt['save']) && $this->_opt['save'] == 2){

            echo '<br><br>Saving to DataBase <b>without Deleting</b> repeating Data...<br><br>';
        
            return;
        
        } 

        /**
         * Process repeats search
         */

        $coinsidence_patterns = array(); 
        
        foreach($this->_checks_array as $check_pattern){

            $coinsidence_patterns[$check_pattern] = $this->findDataRepeats($this->_file, $check_pattern);

           

            // if(!$coinsidence_patterns[$check_pattern]) {
                
            //     echo '<span class="danger-info"><br><br><h3>Can\'t create Compare Pattern to check Data!</h3> <br>Check out $this->_check_patterns Keys corresponds CSV Column Keys</span>';
                
            //     return;
            // }
        } 
             
        
        /**
         * Show Repeats Initial log
         */

        if(!isset($this->_opt['promise'])){
                
            if(isset($this->_opt['show_reps']) && $this->_opt['show_reps'] == 1){

                foreach($this->_checks_array as $check_pattern){

                    $coinsidence_patterns[$check_pattern] = $this->printAttentions($check_pattern);
                    
                }  
            
            }
        }


        /**
         * Process CSV to DB Data
         */
        
        if(isset($this->_opt['save']) && $this->_opt['save'] == 1){ 
            
            if(!isset($this->_opt['promise'])){

                echo '<br><br><span class="info-info">Initial CSV file:</span> '.count($this->_file).' rows';

                $this->printData($this->_file,['mode'=>'show']);

            }

            foreach($this->_checks_array as $check_pattern){              
              
                $deleteFeedback = $this->deleteNonUniqueData($coinsidence_patterns[$check_pattern], $check_pattern);

                if(!isset($this->_opt['promise'])){

                    if($deleteFeedback){
        
                        echo '<br><br><span class="warning-info"><b>Checking Pattern "'.$deleteFeedback['c_pattern'].'"</b> :: unset: '.$deleteFeedback['rows_deleted']. ' rows: '.substr($deleteFeedback['rows_log'],0,strlen($deleteFeedback['rows_log'])-1).'</span>';
                        
            
                        if($deleteFeedback['rows_deleted']){
            
                            $this->printData($this->_file,['mode'=>'show']);

                            echo '<br><span class="warning-info"><b>Deleted pattern:</b> '.$deleteFeedback['compared_row'].' :</span> at row №: '.$deleteFeedback['row_count'];
    
                            echo '<br><span class="info-info">Optimized CSV file:</span> '.count($this->_file).' rows';
                        } 
                    
                    }
                    
                }

                $this->_file = $this->rearrangeArray($this->_file);  
            } 
            

            /* Get DB Data and count DB Data */

            $this->_db_data = $this->_db->getDBData();

            /**
             * Count CSV and DB rows
             */
            
            $db_rows_count = count($this->_db_data);           

            $csv_rows_count = count($this->_file); 
            
           
            if(!isset($this->_opt['promise'])){
                
                echo '<hr><br><span class="info-info">Data statistics</span>: <br> CSV data contains '.$csv_rows_count.' rows <br> DB contains: '.$db_rows_count.' rows';

                    /* If Db Data contains MORE rows that CSV Data - stop file proceeding */
                
                if($db_rows_count > $csv_rows_count){

                    $this->showCSVlessRowsWarning();   
                    
                    return;
                }
            }

            $new_CSV_data_count = $csv_rows_count - $db_rows_count;

            $csv_start_row = $csv_rows_count - $new_CSV_data_count;

            $csv_end_row = $csv_rows_count - 1; 
            
            $this->separateCSVData($csv_start_row); 
            
            if(!isset($this->_opt['promise'])){

                echo '<hr><br>New CSV data contains: '.$new_CSV_data_count.' rows'; 

                echo '<br><br>New data List: ';

                $this->printData($this->_new_file_data,['mode'=>'show']);

                /**
                 * Show "Perform Save to DB" button 
                 * if CSV file contains New Data
                 */
                
                if($this->_new_file_data){
                    
                    echo '<form method="post" action="/data_checker.php?save=1&flush=1&promise=1">
                    <input type="submit" value="Perform Save to DB"/>
                    </form>';
                }

            }
                       

            /* Compare Old CSV Data with DB Data (always Old) */

            $nonidents_array = $this->checkArraysOnIdentity($this->_old_file_data, $this->_db_data);
            
            if(!isset($this->_opt['promise'])){

                echo '<br><br>Old data List: '; 
            
            }           
             
            /* If Old CSV Data and DB Data are identical -> try to flush New CSV Data to DB */

            if(isset($this->_opt['flush']) && $this->_opt['flush'] == 1){

                if(empty($nonidents_array)){ 
                
                    /* Check if CSV file has New Data */    

                    if($this->_new_file_data){                        
   
                        /* Flush New CSV Data into DB */
                    
                        $this->flushToDB();
                        
                        $this->printData($this->_new_file_data,['mode'=>'show']);

                    } 
                    
                }

                else{

                    echo '<br><br><span class="info-info">CSV file has NO exceeding Data. Nothing to add to DB</span>';                    
                }

            }

            if(!isset($this->_opt['promise'])){

                if(!empty($nonidents_array)){

                    echo '<span>DB Update Required!</span>';
                }               
                
                $this->printData($this->_old_file_data,['mode'=>'update', 'data'=>$nonidents_array]);

            }
   

            /* Show preUpdate Dialog (no Update process performed)*/
            if($this->preUpdateRequirements()){                

                $this->preUpdateDialog();                 

            }

            /* Perform Update (flush DB)*/
            if($this->flushUpdateRequirements()){                

                $this->flushUpdate();

                $nonidents_array = $this->checkArraysOnIdentity($this->_file, $this->_db_data);

                if(!isset($this->_opt['promise'])){

                    $this->printData($this->_old_file_data,['mode'=>'update', 'data'=>$nonidents_array]);

                }
            }

            /*  Consistency check: 
                *   CSV file should be absolutely identical to DB content 
                */


                if(!isset($this->_opt['promise'])){

                    $final_check_array = $this->runConsistencyCheck();

                    if(empty($final_check_array)){
        
                        echo '<br><br><span class="success-info">Final check:CSV Data is absolutely identical to DB</span>';
                    }
        
                    else{
        
                        echo '<br><br><span class="danger-info">CSV Data is NOT identical to DB Data. Please, Update your Data</span>';
                    }
        
                }

                echo '<br><br><a href="/data_checker.php" class="btn btn-primary"> Back to Start </a>';
                
        }
        
    }

    private function flushToDB(){

        if($rows_count = $this->_db->CSVtoDB($this->_new_file_data)){

            echo '<br><br><span class="success-info">Successfully added to DB: '.$rows_count.' rows</span>';

        }

        else{

            echo '<br><br><span class="danger-info">Cant perform CSVtoDB operation</span>';
        }
    }

    private function showCSVlessRowsWarning(){

        echo '<br><br><span class="danger-info">CSV file contains LESS ROWS than DB Data. File proceeding ABORTED! 
        <br>Please, check your CSV file</span>';

        $nonidents_array = $this->checkArraysOnIdentity($this->_db_data,$this->_file);

        echo '<br><br><span class="warning-info">First non-identical row in DB: </span>'; 
        
        if(!empty($nonidents_array)){

            $tmp[] = array_shift($nonidents_array);             
                            
            $this->printData( $tmp, ['mode'=>'show']);

        }
        else{

            echo '<span >No non-identical rows found in CSV - DB. <br>Probably, DB contains extra rows omitted in CSV. Check it out!</span>'; 

        }               
        

        echo '<br><br><a href="/data_checker.php" class="btn btn-primary"> Back to Start </a>';
       

    }

    private function preUpdateRequirements(){

        if(isset($this->_opt['save']) && $this->_opt['save'] == 1 && isset($this->_opt['show_reps']) && $this->_opt['show_reps']==0 && isset($this->_opt['update_id'])){

            return true;
        }

        return false;
    }

    private function preUpdateDialog(){

        echo '<br><br><span class="info-info">Updating DB row: '.$this->_opt['update_id'].'</span>';

        $this->_db_data = $this->_db->getDBData($this->_opt['update_id']);            

        echo '<br><br><span class="warning-info">Ready to update DB row: </span>';
        $this->printDataRow($this->_db_data[$this->_opt['update_id']]);                   
        
        echo '<br><span class="warning-info">to CSV row</span>: '.$this->_opt['update_id'];

        $this->printDataRow($this->_old_file_data[$this->_opt['update_id']]);                   

        echo '<form method="post" action="/data_checker.php?save=1&update=2&promise=1&csv_id='.$this->_opt['update_id'].'&db_id='.$this->_db_data[$this->_opt['update_id']]['id'].'">
        <input type="submit" value="Perform Update DB"/>
        </form>';

        return;
    }

    private function runConsistencyCheck(){

        $this->_db_data = $this->_db->getDBData();
                        
        $final_check_array = $this->checkArraysOnIdentity($this->_file, $this->_db_data);  
        
        return $final_check_array;    
                
    }

    private function flushUpdateRequirements(){

        if(isset($this->_opt['update']) && $this->_opt['update']==2 && isset($this->_opt['csv_id']) && isset($this->_opt['db_id'])){

            return true;
        }

        return false;
    }

    private function flushUpdate(){

        $params = $this->_old_file_data[$this->_opt['csv_id']];

        $rows_count = $this->_db->updateDBRow($this->_db_table,$params, $this->_opt['db_id']);

        if(!empty($rows_count)){

            echo '<span class="info-info">DB row:'.$rows_count.' updated successfully</span>'; 
            
            $new_row = $this->_db->getDBDataRow($this->_opt['db_id']);
               

            foreach($new_row as $row){

                foreach($row as $item_key=>$item_value){

                    $output_arr[$item_key]=$item_value;
                }
                
            }

            $this->printDataRow($output_arr);
            
        }

        else{

            echo '<span class="warning-info">No rows affected. Check your Data or DB Connection</span>';
        }  
        
        echo '<br><br><a href="/data_checker.php?save=1&show_reps=0" class="btn btn-success"> Back to Optimizer </a>';

        return;
    }

    private function printData(array $data, array $params){

        $markup_row_num = 0;

        $markup_row_keys = array();

        $row_num = 0;     


        if(!empty($data)){
           
            if($params['mode'] == 'update'){            
            
                foreach($data as $row_key=>$row_values){
                    
                    $data[$row_key]['Update'] = '<span class="success-info">No change</span>';                    

                    if(isset($params['data'][$row_key]) && !empty($params['data'][$row_key])){                        
    
                        $data[$row_key]['Update'] = '<form method="post" action="/data_checker.php?save=1&show_reps=0&update_id='.$row_key.'&promise=1"><input type="submit" value="Update DB"/></form>';
                        
                        $markup_row_keys[] = $markup_row_num;
                    
                    }

                    $markup_row_num++;
                }

            }

            var_dump($markup_row_keys);

            if($data[0]){

                echo '<table class="print_data_table">';

                echo '<tr>';

                /* Set up Table row number column */

                echo '<td> № </td>';

                /* Retrieving Table Head */

                foreach($data[0] as $key=>$value){             

                    echo '<td>'.$key.'</td>';                
                    
                }          
                echo '</tr>';


                /* Retrieving Table Body */


                foreach($data as $key=>$row){                    
                    
                    if($this->checkRowUpdate($row_num,$markup_row_keys)){

                        echo '<tr style="background:red">';

                    }

                    else{

                        echo '<tr>';
                    }                    
                    
                    echo '<td>'.($row_num + 1).'</td>';              
                                    
                    foreach($row as $value){

                    echo '<td>'.$value.'</td>';            
                                
                    }

                    $row_num++;

                    echo '</tr>';
                }          
                
                echo '</table>';

            }

        }
        
        else{

            echo '<span class="success-info">No data to print</span>';
        }
        
    }

    private function boolean checkRowUpdate($row_num,$markup_row_keys){

        foreach($markup_row_keys as $markup_key){

            if($row_num == $markup_key){

                return true;
            }
        }

        return false;
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

            //echo '<br>CSV pattern: '.$test_ar_pattern;

            $etalon_pattern = $this->getIdentityPattern($etalon[$row_count]);

            // echo '<br>DB pattern: '.$etalon_pattern;

            if($test_ar_pattern != $etalon_pattern){
            
                $nonidents_array[$row_count] = $test_array[$row_count];

                // echo '<br>ROW NOT IDENTICAL: '.$etalon_pattern;
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
                echo '<br/><span class="danger-info">Must be deleted '.($row['count']-1).' row(s): '.substr($row['rows'],2, strlen($row['rows'])).'</span>';
    
            }

        }

        else{

            echo '<br/><span class="success-info"> No '.$pattern.' pattern repeats found</span>';

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

        if(!array_key_exists($pattern, $this->_attentions)){            

            return false; 
         } 
        
        
        $source_array = $this->_attentions[$pattern];

        $count_patterns=array();

        $count_patterns_row = 0;

        $hasValue = 0;         
          
        
        if(isset($source_array) && !empty($source_array)){

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
        }
                
       
        return $count_patterns;
    }

    private function findDataRepeats(array $data, string $pattern){        

        $coinsidence_patterns = array();        

        $row_count = 0;       

        foreach($data as $row){

            $compared_pattern = $this->getComparedPattern($row, $pattern);           
                        
            // if(!$compared_pattern) return false;
            
            

            
            $coinsidence_count = 0;

            foreach ($this->_file as $file_item){              

                $item_address = $this->getComparedPattern($file_item, $pattern);

                if($compared_pattern == $item_address){

                    $coinsidence_count++;

                    if($coinsidence_count == 2){                       
                      
                        $this->_attentions[$pattern][$row_count] = $compared_pattern;       
                         
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

        $compared_row = $this->getComparedPattern($value,$c_pattern); 
            
        if($coinsidence_patterns){
           
            foreach($coinsidence_patterns as $pattern){             
    
                if($compared_row == $pattern){                    

                    $repeats_array[$pattern]++;                 

                    if($repeats_array[$pattern] > 1){                        

                        unset($this->_file[$key]);

                        $rows_deleted++;

                        $rows_log .= $row_count.' ,';
                    }
                    
                }                
                    
            } 

        }
            
            $row_count++;

        }     
        
        return [
            
            'c_pattern'=>$c_pattern,

            'rows_deleted'=> $rows_deleted,

            'row_count'=>$row_count,

            'rows_log' => $rows_log,

            'compared_row'=>$compared_row

        ];         
        
    }

    private function getComparedPattern(array $data, string $pattern){

        $pattern_string = ""; 
        
        $pattern_error = array();
               
        foreach($this->_check_patterns[$pattern] as $check_pattern){            

            /* If Pattern Key dont exist (wrong naming) - return false */

            if(array_key_exists($check_pattern, $data)){

                $pattern_string .= $data[$check_pattern]; 
            
            }

            else{ 
                
                $pattern_error[] = $check_pattern;           
               
            }
            
        }

        if(!empty($pattern_error)){

            echo '<span class="danger-info"><b>Pattern Error. Check $this->_check_patterns for following Pattern Keys: </b></span>';

            foreach($pattern_error as $pattern){

                echo $pattern.' , ';
            }

        }           
        
        return $pattern_string;
        
    }

    private function createCoinsPatternsArray($coinsidence_patterns){

        if($coinsidence_patterns){

            $repeats_array = array();

            foreach($coinsidence_patterns as $pattern){

                $repeats_array[$pattern] = 0;
            }

            return $repeats_array;

        }

    }

    private function rearrangeArray($orig_array){

        $rearranged_array = array();

        foreach($orig_array as $item){

            $rearranged_array[] = $item;
        }

        return $rearranged_array;
    }
}