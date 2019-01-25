<?php

require_once($_SERVER['DOCUMENT_ROOT'].'/models/CSVModel.php');


if(isset($_GET['slug'])){   
 
    $csv = new CSVClass;   

    $csv->getCityFromDB($_GET['slug']);

}


class CSVClass{

    private $_file_url = "/data/test.csv";

    private $_init_file = array();

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

        $this->readCSVfile($this->_file_url);

        /**
         * Copy initial CSV file Data into $this->_init_file
         */

        $this->_init_file = $this->_file;

        /**
         * Initialize DB Class
         */

        $this->_db = new CSVModel; 
       
    }

    public function getOpts(){

        return $this->_opt;

    }

    public function checkFileLoaded(){

        return ($this->_file)? true : false;
    }

    public function processCSVFile(){  

        /**
         * Die process if CSV File no found
         */

       if (!$this->checkFileLoaded()) {       

        echo '<h4><span class="danger-info">Crytical Error: File load failure. Check path: .'.$this->_file_url.'</span></h4>';
           
        return;

        };      

              
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

        } 
             
        
        /**
         * Show Repeats Initial log
         */        
        
        if(!isset($this->_opt['promise'])){
                
            if(isset($this->_opt['show_reps']) && $this->_opt['show_reps'] == 1){                
                
                if($markup_rows = $this->getMarkupRows()){                    

                    foreach($markup_rows as $markup_pattern_key=>$value){

                        if(!$this->printPatternAttentions($markup_pattern_key)){

                            echo '<hr><h4><span class="success-info"> No repeating rows found in CSV file in '.$markup_pattern_key.' pattern</h4><hr>';
                        }
                    }                    
                }
                
                else{

                    echo '<h4><span class="success-info"> No repeating rows found in CSV file in ANY pattern</span></h4>';
                }
                
                                                
                $this->printData($this->_file,['mode'=>'show', 'start'=>0, 'markup_rows'=>$markup_rows]);
                
            }
        }


        /**
         * Process CSV to DB Data
         */
               
        if(isset($this->_opt['save']) && $this->_opt['save'] == 1){ 
            
            if(!isset($this->_opt['promise'])){

                echo '<br><br><span class="info-info"><b>Initial CSV file:</b></span> '.count($this->_file).' rows';                

                $markup_rows = $this->getMarkupRows();
               
                $this->printData($this->_file,['mode'=>'show', 'start'=>0, 'markup_rows'=>$markup_rows]);

            }

            foreach($this->_checks_array as $check_pattern){ 
                              
                $deleteFeedback = $this->deleteNonUniqueData($coinsidence_patterns[$check_pattern], $check_pattern);                

                if(!isset($this->_opt['promise'])){

                    if($deleteFeedback){
        
                        echo '<hr><h3><span class="warning-info">Checking Pattern "'.$deleteFeedback['c_pattern'].'"</h3></span>';

                        $markup_row_num = $this->printMarkupRowNum($check_pattern, $markup_rows);

                        echo ($markup_row_num)?'<span class="info-info"><b>Initially detected</b> repeated rows: '.$markup_row_num.'</span>':'';
                        
            
                        if($deleteFeedback['rows_deleted']){                           
                            
                            echo '<br><br><span class="warning-info"><b>Currently deleted pattern(s):</b> '.$deleteFeedback['compared_row'].' :</span>:: <span class="danger-info"><b>unset: '.$deleteFeedback['rows_deleted']. ' row(s) : rows №: '.substr($deleteFeedback['rows_log'],0,strlen($deleteFeedback['rows_log'])-1).'</b></span><br><br>';

                            $rows_to_print = explode(',',substr($deleteFeedback['rows_log'],0,strlen($deleteFeedback['rows_log'])-1));

                            /**
                             * Print deleted repeated row of CSV file
                             */

                            $this->printDataRows($rows_to_print,$this->_init_file[$deleteFeedback['deleted_row']], ['row_num'=>$rows_to_print, 'row_style'=>'deleted', 'table_header'=>'common']);

                            echo '<br><span class="info-info"><b>Optimized CSV file:</b></span> '.count($this->_file).' rows<br><br>';

                            /**
                             * Get and Print markuped Pattern rows
                             */

                            $markup_rows = $this->getMarkupRows(); 
                                                        
                            $this->printData($this->_file,['mode'=>'show', 'start'=>0, 'markup_rows'=>$markup_rows, 'patt_type'=>$check_pattern]);
                            
                        } 

                        else{

                            echo "<br><br><span class='success-info'><b>No repeated rows occured</b> OR had been deleted (if existed) in previous checks</span><br><br>";
                        
                            /**
                             * Print Non-markuped Pattern rows
                             */
                            
                            $this->printData($this->_file,['mode'=>'show', 'start'=>0, 'no_rows_markup'=>true, 'patt_type'=>$check_pattern]);
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
                
                echo '<hr><h4><span class="info-info"><b>Data statistics:</b></span> </h4> CSV data contains '.$csv_rows_count.' rows <br> DB contains: '.$db_rows_count.' rows';

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

                echo '<hr><h4>New CSV data contains: '.$new_CSV_data_count.' rows</h4>'; 

                echo '<h4>New data List: ';

                $this->printData($this->_new_file_data,['mode'=>'show', 'no_rows_markup'=>true, 'markup_rows'=>[]]);                

                echo '</h4>';

                /**
                 * Show "Perform Save to DB" button 
                 * if CSV file contains New Data
                 */
                
                if($this->_new_file_data){
                    
                    echo '<form method="post" action="/data_checker.php?save=1&flush=1&promise=1">
                    <input type="submit" class="btn btn-success" value="Perform Save to DB"/>
                    </form>';
                }

            }
                       

            /* Compare Old CSV Data with DB Data (always Old) */

            $nonidents_array = $this->checkArraysOnIdentity($this->_old_file_data, $this->_db_data);
            
            if(!isset($this->_opt['promise'])){

                echo '<hr><h4>Old data List: </h4>'; 
            
            }           
             
            /* If Old CSV Data and DB Data are identical -> try to flush New CSV Data to DB */

            if(isset($this->_opt['flush']) && $this->_opt['flush'] == 1){

                if(empty($nonidents_array)){ 
                
                    /* Check if CSV file has New Data */    

                    if($this->_new_file_data){                        
   
                        /* Flush New CSV Data into DB */
                    
                        $this->flushToDB();
                        
                        $this->printData($this->_new_file_data,['mode'=>'show','']);

                    } 
                    
                }

                else{

                    echo '<br><br><span class="info-info">CSV file has NO exceeding Data. Nothing to add to DB</span>';                    
                }

            }

            if(!isset($this->_opt['promise'])){

                if(!empty($nonidents_array)){

                    echo '<span class="warning-info"><h4>DB Update Required!</h4></span>';
                }  
                                               
                $this->printData($this->_old_file_data,['mode'=>'update', 'data'=>$nonidents_array, 'no_rows_markup'=>true, 'markup_rows'=>$this->getMarkupRows()]);

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

                    echo '<hr><h4><span class="info-info">Final check::</span></h4>';
                   
                    $final_check_array = $this->runConsistencyCheck();

                    if($final_check_array === false){

                        echo '<h4><span class="warning-info">No DB Data found</span></h4>';
                    } 

                    else{
                        
                        if(empty($final_check_array)){
            
                            echo '<hr><h4><span class="success-info">Final check:CSV Data is absolutely identical to DB</span></h4>';
                        }
            
                        else{
            
                            echo '<hr><span class="danger-info"><h4>Crytical Error:</h4>CSV Data is NOT identical to DB Data. Please, Update your Data</span>';
                        }
                    }

                    
        
                }

                echo '<br><br><a href="/data_checker.php" class="btn btn-primary"> Back to Start </a>';
                
        }
        
    }

    private function getMarkupRows(){         
        
        foreach($this->_checks_array as $check_pattern){

            if(!empty($check_pattern)){

                $markup_rows[$check_pattern] = $this->getAttentions($check_pattern);
                
            }             
                      
        } 
        
        /**
         * Check if $markup_rows Array contains any values
         */
        $markup_rows_values = false;
        
        foreach ($markup_rows as $markup_row){

            if(!empty($markup_row)){

                $markup_rows_values = true;
            }

        }
        
        
        if($markup_rows_values){

            /* Placing Reps rows into $reps_rows Array */

            foreach($markup_rows as $markup_key=>$markup_value){ 
                
                if(!empty($markup_value)){

                    foreach($markup_value as  $item){                    

                        $temp_rows = explode(',',$item);

                        $all_markup_rows[$markup_key][] = $temp_rows;

                    }  
                    
                }
            }

            return $all_markup_rows;
        }  

       return [];
    }


    private function printDataRows(array $rows_to_print, array $source_array, array $params){

        // echo '<pre>';
        // print_r($params);
        // echo'</pre>';

        $row_count = 0;

        $row_nums = $params['row_num'];

        foreach($rows_to_print as $row_to_print){

            if(isset($params['table_header']) &&  $params['table_header'] === 'common'){

                if( $row_count == 0){                   

                    $params['first_row'] =  $params['row_num'][$row_count]; 
                }   
                
            } 
            
            $params['row_num'] =   $row_nums[$row_count];                     

            $this->printDataRow($source_array, $params);
            
            $row_count++;
        }  
    }

    private function printMarkupRowNum(string $pattern, array $markup_rows){        

        $rep_string="";        

        $markup_rows = $this->getMarkupRows();

        if(array_key_exists($pattern,$markup_rows)){

            foreach($markup_rows[$pattern] as $markup_rows){

                foreach($markup_rows as $markup_key=>$markup_row){

                    if($markup_key >= count($markup_rows)-1){

                        $rep_string .= $markup_row;
                    }

                    else{

                        $rep_string .= $markup_row.",";
                    }
                    
                    
                }

                $rep_string .= ' : ';
            }

        return substr($rep_string,0,strlen($rep_string)-3);

        }

        return false;

                        
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

        echo '<hr><span class="warning-info">Last identical row in DB: </span>'; 

        /**
         * Print last CSV-DB identical row
         */
        
        if(!empty($nonidents_array)){

            $nonident_row_id = $this->getFirstNonIdentRow($nonidents_array); 

            $last_ident_row = $this->getLastIdenticalRowData($this->getLastIdenticalRowNum($nonident_row_id));          
            
            if($last_ident_row) $this->printDataRow( $last_ident_row, ['row_style'=>'replace','first_row'=>0,'row_num'=>0]);                              

        }
        else{

            echo '<span >No non-identical rows found in CSV - DB. <br>Probably, DB contains extra rows omitted in CSV. Check it out!</span>'; 

        }               
        

        echo '<br><br><a href="/data_checker.php" class="btn btn-primary"> Back to Start </a>';
       

    }

    private function getLastIdenticalRowData(int $last_row_num){

        $row_count = 0;

        foreach($this->_db_data as $db_row){

            if($row_count == $last_row_num){

                return $db_row;
            }

            $row_count++;
        }

    }

    private function getLastIdenticalRowNum(int $nonident_row_id){

        $row_count = 0;
            
        foreach($this->_db_data as $db_row){

            if($db_row['id'] == $nonident_row_id){

                $last_row_id = $row_count-1;

                return  $last_row_id;
            }

            $row_count++;
        }

        return false;
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
        $this->printDataRow($this->_db_data[$this->_opt['update_id']], ['row_style'=>'updated','first_row'=>$this->_opt['update_id'],'row_num'=>$this->_opt['update_id'],'exclude_keys'=>['id']]);                   
        
        echo '<br><span class="warning-info"> --> to CSV row</span>: '.$this->_opt['update_id'];

        $this->printDataRow($this->_old_file_data[$this->_opt['update_id']], ['row_style'=>'replace','first_row'=>$this->_opt['update_id'],'row_num'=>$this->_opt['update_id']]);                   

        echo '<form method="post" action="/data_checker.php?save=1&update=2&promise=1&csv_id='.$this->_opt['update_id'].'&db_id='.$this->_db_data[$this->_opt['update_id']]['id'].'">
        <br><br><input type="submit" class="btn btn-warning" value="Perform Update DB"/>
        </form>';

        return;
    }

    private function runConsistencyCheck(){

        $this->_db_data = $this->_db->getDBData();
        
        /**
         * Return false if no Db Data found (nothing to compare with CSV Data)
         */
        
        if(empty( $this->_db_data)) return false;

        /**
         * Return $final_check_array 
         */

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

            echo '<span class="success-info">DB row:'.$rows_count.' updated successfully</span>'; 
            
            $new_row = $this->_db->getDBDataRow($this->_opt['db_id']);
               

            foreach($new_row as $row){

                foreach($row as $item_key=>$item_value){

                    $output_arr[$item_key]=$item_value;
                }
                
            }

            $this->printDataRow($output_arr, ['row_style'=>'replace','first_row'=>$this->_opt['db_id'],'row_num'=>$this->_opt['db_id']]);
            
        }

        else{

            echo '<span class="warning-info">No rows affected. Check your Data or DB Connection</span>';
        }  
        
        echo '<br><br><a href="/data_checker.php?save=1&show_reps=0" class="btn btn-success"> Back to Optimizer </a>';

        return;
    }

    private function getFirstNonIdentRow($nonidents_array){

        $tmp = array_shift($nonidents_array); 
        
       return $tmp['id'];                            
        
    }

    
    private function printData(array $data, array $params){

        // echo '<pre>';
        // print_r($data);
        // echo'</pre>';


        if(!isset($params['markup_rows'])){

            $markup_row_keys = array();
        }
        
        else{

            $markup_row_keys = $params['markup_rows'];
        }



        /** Get Rows Markup Table */

        if(isset($params['patt_type'])){

            $rows_markup_array =  $this->getRowsTypeMarkupArray($data,$markup_row_keys, $params['patt_type']);

        }

        else{
            
            $rows_markup_array =  $this->getRowsMarkupArray($data,$markup_row_keys);

        }
        
        
        /** Setting to  $data[$row_key]['Update'] Update button code of 'No change' label */
      
        if(!empty($data)){
           
            if($params['mode'] == 'update'){            
            
                foreach($data as $row_key=>$row_values){
                    
                    $data[$row_key]['Update'] = '<span class="success-info">No change</span>';                    

                    if(isset($params['data'][$row_key]) && !empty($params['data'][$row_key])){                        
    
                        $data[$row_key]['Update'] = '<form method="post" action="/data_checker.php?save=1&show_reps=0&update_id='.$row_key.'&promise=1"><input type="submit" class="btn btn-warning" value="Update DB"/></form>';
                                            
                    }                  
                }

            }            

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
                
                $row_num = 0;

                foreach($data as $key=>$row){                  
                       

                    /* Check if should markup updated row */
                    
                    if(!empty($rows_markup_array)){

                        $this->printMarkupedRow($row_num, $rows_markup_array, $params);                        
                    }


                    /* Start rows number from 0 or 1 depending on $params['start'] */                 
                                        
                    echo(!isset($params['start']))?'<td>'.($row_num + 1).'</td>':'<td>'.$row_num.'</td>'; 
                                    
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

    private function printMarkupedRow(int $row_num, array $rows_markup_array, array $params){

        if($row_type = $this->checkRowMarkup($row_num,$rows_markup_array)) {
                       
            /**
             * if printData func was called with params['no_rows_markup'] - no row markup applied
             */

            if(isset($params['no_rows_markup']) && $params['no_rows_markup'] === true){$row_type['type'] = "default";}

            switch($row_type['type']){

                case "row_first_num":

                 echo '<tr style="background:rgba(0,128,0,'.(0.3*$row_type['count']).');">';

                 break;

                 case "row_second_num":

                 echo '<tr style="background:rgba(255,0,0,'.(0.3*$row_type['count']).');">';

                 break;
                 
                 case "row_mixed":

                 echo '<tr style="background:rgba(255,127,80,'.(0.3*$row_type['count']).');">';

                 break; 

                 default:

                 echo '<tr>';

            }                                                             
        
        } 

    }

    private function checkRowMarkup(int $row_num,array $rows_markup_array){

        // echo'<pre>';
        // print_r($rows_markup_array);
        // echo'</pre>';

        $row_type = array();

        $type_count = 0;

        foreach($rows_markup_array as $rows_markup_pattern){

            foreach($rows_markup_pattern as $rows_markup_type=>$rows_markup_vals){

                foreach($rows_markup_vals as $row_val){

                    if($row_num == $row_val){

                        $type_count++;                        

                        $row_type=['type'=>$rows_markup_type, 'count'=>$type_count];                    
                    }
                }                    
            }                      
        }

        return $row_type;       
    }


    private function getRowsTypeMarkupArray(array $data,array $markup_row_keys, string $type){       

        $row_nums = array_keys($data);
        
        if(array_key_exists($type, $markup_row_keys)){

            $markup_type_row_keys[$type] = $markup_row_keys[$type];
        
        // echo '<pre>';
        // print_r($markup_type_row_keys);
        // echo'</pre>';        

            $markup_row = array();

            foreach($row_nums as $row_num){

                foreach($markup_type_row_keys as $patt_key=>$patt_row){ 
                
                    //echo '<br>Checking pattern: '. $patt_key.' for ROW NUM: '.$row_num;               
                    
        
                    foreach($patt_row as $row){

                        $perPattern_rows_count = 0;
        
                        foreach($row as $item){
        
                            if($row_num == $item && strlen($item)){
                            
                                if($perPattern_rows_count == 0){
            
                                    //echo '<br>perPattern_rows_count: '.$perPattern_rows_count;
            
                                    //echo '<br>Check $row_num: '.$row_num.' with : '.$item.' :: MARK FIRST ROW';
            
                                    $markup_row[$patt_key]['row_first_num'][] = $row_num;                           
            
                                } 
                                
                                else{
        
                                // echo '<br>Check $row_num: '.$row_num.' with : '.$item.' :: ELSE ROW';
                                
                                $markup_row[$patt_key]['row_second_num'][] = $row_num;  
                                }                            
                                                    
                            }
        
                            $perPattern_rows_count++;
            
                        }
                    }  
        
                }
            }

            return ($markup_row)? $markup_row:false;
        }
        
    }



    private function getRowsMarkupArray(array $data,array $markup_row_keys){       

        $row_nums = array_keys($data); 
        
        $markup_rows_array = array();

        foreach($row_nums as $row_num){

            foreach($markup_row_keys as $patt_key=>$patt_row){ 
            
                //echo '<br>Checking pattern: '. $patt_key.' for ROW NUM: '.$row_num;               
                
    
                foreach($patt_row as $row){

                    $perPattern_rows_count = 0;
    
                    foreach($row as $item){
    
                        if($row_num == $item && strlen($item)){
                        
                            if($perPattern_rows_count == 0){
        
                                //echo '<br>perPattern_rows_count: '.$perPattern_rows_count;
        
                                //echo '<br>Check $row_num: '.$row_num.' with : '.$item.' :: MARK FIRST ROW';
        
                                $markup_rows_array[$patt_key]['row_first_num'][] = $row_num;                           
        
                            } 
                            
                            else{
    
                               // echo '<br>Check $row_num: '.$row_num.' with : '.$item.' :: ELSE ROW';
                            
                               $markup_rows_array[$patt_key]['row_second_num'][] = $row_num;  
                            }                            
                                                   
                        }
    
                        $perPattern_rows_count++;        
                    }
                }
            }
        }

        // echo '<pre>';
        // print_r($markup_rows_array);
        // echo '<pre>';

        /**
         * Check if $markup_rows_array contains mixed_rows
         * If contains - override $markup_rows_array with $markup_with_mixed_array
         */

        $markup_with_mixed_array= $this->checkMixedRows($markup_rows_array);

        if($markup_with_mixed_array) $markup_rows_array = $this->checkMixedRows($markup_rows_array);

        // echo '<pre>';
        // print_r($markup_rows_array);
        // echo '<pre>';

        return ($markup_rows_array)? $markup_rows_array:false;

    }

    private function checkMixedRows($markup_rows_array){

        /**
         * Check mixed type rows
         */

        $curr_scnd_nums = array();
        $mixed_rows = array();

        /**
         * Get all first_num rows into Array
         */

        foreach($markup_rows_array as $key=>$markup_row_patt){            

           if(isset($markup_row_patt['row_second_num']) && !empty($markup_row_patt['row_second_num'])){

                foreach($markup_row_patt['row_second_num'] as $value){

                    $curr_scnd_nums[] =$value;

                }
                           
            }
        }

        if(isset($curr_scnd_nums) && !empty($curr_scnd_nums)) {

            $curr_scnd_nums = array_unique($curr_scnd_nums);

            /**
             * Get all second_num rows where first_nums Array items are presented
             */

            foreach($markup_rows_array as $key=>$markup_row_patt){   
                
                //echo '<br>checking f_n_pattern: '.$key;

                if(isset($markup_row_patt['row_first_num']) && !empty($markup_row_patt['row_first_num'])){                

                    foreach($markup_row_patt['row_first_num'] as $f_n_value){

                        //echo '<br>checking f_n_row: '.$f_n_value;
    
                        foreach($curr_scnd_nums as $s_n_key=>$s_n_value){

                            if(in_array($s_n_value,$markup_row_patt['row_first_num'])){

                                //echo '<br>found scnd num row value: '.$s_n_value.' in first_num row value: '.$f_n_value;

                                $s_n_to_mixed[] = $f_n_value;
                            }                            
                        }                    
                    }
                }
            }

            if(isset($s_n_to_mixed) && !empty($s_n_to_mixed)) {

                $s_n_to_mixed = array_unique($s_n_to_mixed);
    
                /**
                 * Unsetting first_num and second_num rows if ther are of mixed_type
                 * Setting mixed_row types in proper $markup_row_patt_key
                 */
    
                foreach($markup_rows_array as $markup_row_patt_key=>$markup_row_patt){
    
                    foreach($markup_row_patt as $markup_row_type_key=>$row_item){
    
                        foreach($row_item as $item_key=>$item){
    
                            foreach($s_n_to_mixed as $s_n_item){
    
                                if($s_n_item == $item){   
                                    
                                    /**
                                     *  Unset first_num Array item if its mixed
                                     */
                                    
                                    unset($markup_rows_array[$markup_row_patt_key][$markup_row_type_key][$item_key]);
    
                                    /**
                                     *  Unset markup_row_type_key in Array it became empty
                                     */
    
                                    if(empty($markup_rows_array[$markup_row_patt_key][$markup_row_type_key])){
    
                                        unset($markup_rows_array[$markup_row_patt_key][$markup_row_type_key]);
                                    }
    
                                    $markup_rows_array[$markup_row_patt_key]['row_mixed'][] = $item;
                                }
                            }
                        }
                    }
                }

                // echo '<pre>';
                // print_r($markup_rows_array);
                // echo'</pre>';

                return  $markup_rows_array;
            }

            return false;
        } 

        return false;
        
        

        
    }


    private function printDataRow(array $data, array $params){  
        
        // echo '<pre>';
        // print_r($params);
        // echo'</pre>';
      

        if($data){          

            echo '<table class="print_data_table">'; 

            /**
             * Printing Row table Header
             */

            /* If exists - Add row_num to the Table */
            if(isset($params['first_row']) && $params['first_row'] === $params['row_num']){

                /* Retrieving Table Head */
                echo '<tr>';

                echo (isset($params['row_num']))?'<td> № </td>':'';           

                foreach($data as $key=>$value){             

                    /**
                    * Apply $params['exclude_keys'] - exclude printing fields set in $params['exclude_keys']
                    */

                    if(isset($params['exclude_keys'])){

                        echo(!$this->checkExcludedKeys($key, $params['exclude_keys']))?'<td>'.$key.'</td>':'';
                    
                    }

                    else{

                        echo '<td>'.$key.'</td>';
                    }                 
                    
                }          
                echo '</tr>';

            }

            else{

                /* Retrieving Hidden Table Head */
                echo '<tr style="visibility:hidden; line-height:1px; padding:0px">';

                echo (isset($params['row_num']))?'<td style="padding:0px 5px"> № </td>':'';           

                foreach($data as $key=>$value){  
                               

                    echo '<td style="padding:0px 5px">'.$key.'</td>';                
                    
                }          
                echo '</tr>';


            }
            
            /** 
             * Applying Row style 
             */
            
             if(isset($params['row_style'])){

                switch($params['row_style']){

                    case 'deleted':

                    echo'<tr style="background:rgba(255,0,0,0.3)">'; 

                    break;

                    case 'updated':

                    echo'<tr style="background:rgba(255,69,0,0.3)">';

                    break;

                    case 'replace':

                    echo'<tr style="background:rgba(0,255,127,0.3)">';

                    break;
                }
             }             
             
             /* If exists - Add row_num to the Table */           

            echo (isset($params['row_num']))?'<td>'.$params['row_num'].'</td>':'';


             /* Retrieving Table Body */
            foreach($data as $key=>$row){

                /**
                 * Apply $params['exclude_keys'] - exclude printing fields set in $params['exclude_keys']
                 */

                if(isset($params['exclude_keys'])){

                    echo(!$this->checkExcludedKeys($key, $params['exclude_keys']))?'<td>'.$row.'</td>':'';
                  
                }

                else{

                    echo '<td>'.$row.'</td>';
                }               
                
                                      
            } 
            
            echo '</tr>';
            
            echo '</table>';

        }
        
        else{

            echo '<span class="success-info">No data to print</span>';
        }
        
    }

    private function checkExcludedKeys(string $key, array $ex_keys){

        if(isset($ex_keys) && !empty($ex_keys)){ 

            foreach($ex_keys as $ex_key){

                if($key == $ex_key){

                    return true;
                }
            }

        return false;
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

    private function readCSVfile(string $url){

        $row = 0;

        if(file_exists ($_SERVER['DOCUMENT_ROOT'].$url)){

            $handle = fopen($_SERVER['DOCUMENT_ROOT'].$url, "r");

            if($handle){
            
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

        else{

            return false;
        }

    }

    public function getCityFromCSV($slug){

        $response = array();

        foreach($this->_file as $row){

            if($row['city'] == $slug){

                $response[] = $row;
            }
        }

        echo json_encode($response);
    } 


    public function getCityFromDB($slug){

        $response = array();       

        $response = $this->_db->getPointsByName($slug);      

        echo json_encode($response);
    }


    private function getAttentions($pattern){ 
        
        $count_patterns = $this->getCountPatterns($pattern);
        
        if(!empty($count_patterns)){

            return $this->getAttentionsRow($pattern, $count_patterns);

        }
        
        return false;
             
    }

    private function getAttentionsRow(string $pattern, array $count_patterns){

        $reps_rows = array(); 

        foreach($count_patterns as $row){                

            $row['rows'] = str_replace(",,","",$row['rows']);
                                                  
            if(isset($row['rows']) && !empty($row['rows'])){                
            
                $reps_rows[] = $row['rows'];

            }
        }       
               
        return $reps_rows;
    }


    private function printPatternAttentions(string $pattern){

        $reps_rows = array(); 

        $count_patterns = $this->getCountPatterns($pattern);

        echo '<br><span class="warning-info"><h3>ATTENTION!</h3></span>';
        
        if(!empty($count_patterns)){

            foreach($count_patterns as $row){
                
                if(isset($row) && !empty($row)){

                $row['rows'] = str_replace(",,","",$row['rows']);
    
                echo '<br><b><span class="warning-info">'.$row['count'].' Repeating pattern " '.$pattern.' " ('.$row['pattern'].') found at CSV rows: '.$row['rows'].' </b>(Rows '.$this->getCSVRows($row['rows']).' in CSV file)</span>';
                echo '<br><br><span class="danger-info">Must be deleted '.($row['count']-1).' row(s): '.substr($row['rows'],2, strlen($row['rows'])).'</span><br><br>';
                echo'<hr>'; 
                
                return true;
                
                }

                return false;
                
            }
            
        }
        
        else{

            return false;
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
    
                    $count_patterns[$count_patterns_row]['rows'] = ",";
    
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
    
                            $count_patterns[$pat_key]['rows'] .= ','.$key;
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

        $deleted_row = 0;

        $rows_log = "";
      
        foreach ($this->_file as $key=>$value){            

        $compared_row = $this->getComparedPattern($value,$c_pattern); 
            
        if($coinsidence_patterns){
           
            foreach($coinsidence_patterns as $pattern){             
    
                if($compared_row == $pattern){                    

                    $repeats_array[$pattern]++;                 

                    if($repeats_array[$pattern] > 1){                        
                     
                        unset($this->_file[$key]); 
                        
                        $deleted_row =  $row_count;
                       
                        $rows_deleted++;

                        $rows_log .= $row_count.',';
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

            'deleted_row'=>$deleted_row,

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