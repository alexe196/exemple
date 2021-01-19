<?php
class RecourceIntegrationExpedia
{
   // PRIVATE PROPERTIES
	var $_DS;
	var $_controller;
	var $_config;
    var $count_calendar = 6;
	// PRIVATE METHODS
	// CONSTRUCTOR
    /**
    * Constructor 
    *
    * Initializes the object
    *
    */	
	function __construct()
	{
		global $CORE;
		$this->_controller = &$CORE;
		$this->_DS = new DataSource('main');
        $this->_dataIntegration = new dataIntegration();
        $this->_expediajson = new ExpediaJson();
		$this->_config = $this->_controller->getConfig();
		$this->_user = $this->_controller->getUser();
	}
    
   function set_hotelid($ResourceOfferID){
       $DS = &$this->_DS; 
       if ( !empty($ResourceOfferID) ){
           $result = $DS->query('SELECT expedia_type_hotel FROM sourcesdb_expedia_id WHERE recourceoffers_id = '.$ResourceOfferID);
       }
       
       return !empty($result[0]['expedia_type_hotel']) ? $result[0]['expedia_type_hotel'] : 0;
   }
   
   /* Формируем JSON спискок квратир на 9flats */
   function createPropertyExpedia($ResourceOfferID)
   {
      $ResourceOfferID = 4051;
      $DS = &$this->_DS; 
      $property = $this->_dataIntegration;
      $nineflatjson = $this->_expediajson;
      $prop = array();
      $manage_db = $this->get_managerExpedia();

      $prop['agency'] = explode(" ", trim($manage_db[0]['AdminResourcImportFIO']) );
      
      $prop['office_address'] = $manage_db[0]['AdminResourcImportAddress'];
      $prop['opening_hours'] = $manage_db[0]['AdminResourcImportHour'];
      $prop['country'] = 'Ukraine';
      $prop['city'] = 'Kiev';
      $prop['currency'] = $manage_db[0]['9flatsPrepaymentValuta'];
      $prop['district'] = 'center';  
      $prop['phone1'] = $manage_db[0]['AdminResourcImportTel1'];
      $prop['phone2'] = $manage_db[0]['AdminResourcImportTel2'];
      $prop['phone3'] = $manage_db[0]['AdminResourcImportTel3'];
      
      $prop['email1'] = $manage_db[0]['AdminResourcImportEmail1'];
      $prop['email2'] = $manage_db[0]['AdminResourcImportEmail2'];
      
      if( !empty($prop['phone1']) )
      {
         $prop['phone'] = $prop['phone1'];
      }
      if( !empty($prop['phone2']) )
      {
         $prop['phoneExtra'] = $prop['phone2'];
      }
  
      $flagResourceOfferID = $ResourceOfferID;    
      if( !empty($ResourceOfferID) )
      {
         $filter = " AND resourceoffer.ResourceOfferID = ".$ResourceOfferID;
      } 
          
      $sql = "SELECT * FROM resourceoffer
             LEFT JOIN region on region.RegionID = resourceoffer.ResourceOfferAddress  
             LEFT JOIN resourceofferordermanagerpersone ON 	resourceofferordermanagerpersone.ResourceOfferID = resourceoffer.ResourceOfferID
             WHERE resourceoffer.PermAll = '1' AND resourceoffer.ResourceofferDone = 1 ".$filter." ORDER BY resourceoffer.ResourceOfferAlias ASC"; 

      $dataOffersRow = $DS->query($sql); 
       $t = 0;
      foreach($dataOffersRow as $row)
      {
         $roomcount = 0;
         $ResourceOfferID = $row['ResourceOfferID'];         
         $prop['minimum_nights'] = $property->get_minDay( 'expediaMinDay', 'sourcesdb_expedia', $ResourceOfferID );
         $integrationAll = $property->get_integrationAll($ResourceOfferID);
         $prop['ResourceOfferZip'] = $row['ResourceOfferZip'];
        
         $prop['id'] =  $row['ResourceOfferID'];
         $prop['persons'] = $row['ResourceOffernumberOfBeds'];
         if( empty($prop['persons']) ){$prop['persons'] = 3;}
         $prop['bedrooms'] =  $row['ResourceOfferBedrooms'];
         
         if(  $row['ResourceOfferBathroom1'] ) { $ResourceOfferBathroom++; }
         if(  $row['ResourceOfferBathroom2'] ) { $ResourceOfferBathroom++; }
      
         $prop['bathrooms'] = $ResourceOfferBathroom;
         $prop['square'] =  $row['ResourceOfferSquare'];
         $prop['floor'] =  $row['ResourceOfferFloor'];
         
         $arr['ResourceOfferAddressGet'] = getValue($row['RegionName'],'en');
         $arr['ResourceOfferAddressGet'] = str_replace("str.", "", $arr['ResourceOfferAddressGet'] );
         if(!empty( $row['ResourceOfferBuildingNumber']) && !empty($arr['ResourceOfferAddressGet']))
         {
            $arr['ResourceOfferAddressGet'] =  $row['ResourceOfferBuildingNumber'].' '.$arr['ResourceOfferAddressGet'];
         }
         
         $prop['address'] = $arr['ResourceOfferAddressGet'];
         $prop['Latitude'] =  $row['ResourceOfferLatitude'];
         $prop['Longitude'] =  $row['ResourceOfferLongtitude'];
         $prop['texts_title'] =  $row['ResourceOfferApartmens50'];
         
         /* description */
         $prop['link'] = 'https://apartments.com.ua/offer-i-apartmentID-i-'.$row['ResourceOfferAlias'].'.html';
         $prop['desc'] = $row['ResourceOfferDescription700']."\n\n".$row['ArriveAtTheProperty']."\n\n".$row['ResourceOfferDescriptionArea']."\n\n".$row['PickUpService'];
         $prop['houserules'] = $row['ArriveAtTheProperty'];
         /* end */
         
         $prop['photo'] = $this->set_photo($ResourceOfferID);
      
         
         $prop['cancellation_rules'] = 'strict';
         switch ($row['ResourceOfferConditionCancellation']) {
            case 1:
                $prop['cancellation_rules'] = 'flexible'; 
                break;
            case 2:
                $prop['cancellation_rules'] = 'semi_flexible';
                break;
            case 3:
                $prop['cancellation_rules'] = 'strict';
                break;
            case 4:
                $prop['cancellation_rules'] = 'strict';
                break;
         }
         
         if( !empty( $row['ResourceOfferDoublebeds']) ){ $prop['ResourceOfferDoublebeds'] =  $row['ResourceOfferDoublebeds']; }
         if( !empty( $row['ResourceOfferSinglebeds']) ){ $prop['ResourceOfferSinglebeds'] =  $row['ResourceOfferSinglebeds']; }
         
         if( !empty( $row['BedroomAdition']) )
         { 
            if( strpos( $row['BedroomAdition'], 'singlesofa'))
            {
               $prop['singlesofa']++;
               //$amenityArr[] = $this->set_amenitys('ROOM_SOFA_BED', "SOFA_BED");  
            } 
            if( strpos( $row['BedroomAdition'], 'two-single-sofa'))
            {
               $prop['singlesofa'] = $prop['singlesofa'] + 2;
            } 
            
            if( strpos( $row['BedroomAdition'], 'doublesofa'))
            {
               $prop['doublesofa']++;
            } 
            if( strpos( $row['BedroomAdition'], 'two-double-sofa'))
            {
               $prop['doublesofa'] = $prop['doublesofa'] + 2;
            } 
         }

         if(!empty($ResourceOfferBathroom)){
            $amenityArr[] = $this->set_amenitys('ROOM_SECOND_BATHROOM', null);
         }
         
         if( $arr['ResourceOfferLivingRoom'] > 0 ){
            $amenityArr[] = $this->set_amenitys('ROOM_LIVING_ROOM', $arr['ResourceOfferLivingRoom']);
         }
         
                  if( $arr['ResourceOfferLivingRoom'] > 0 ){
            $amenityArr[] = $this->set_amenitys('ROOM_LIVING_ROOM', $arr['ResourceOfferLivingRoom']);
         }
         
         if( !empty( $row['ResourceOfferBathroom1']) ){
            $roomcount++;
         }
         if( !empty( $row['ResourceOfferBathroom2']) ){
            $roomcount++;
         }
         
         $amenityArr[] = $this->set_amenitys('ROOM_NUMBER_OF_BATHROOMS', $roomcount);
         $amenityArr[] = $this->set_amenitys('ROOM_KITCHEN', "KITCHEN");
         $amenityArr[] = $this->set_amenitys('ROOM_DISHWARE', null);
         $amenityArr[] = $this->set_amenitys('ROOM_DISHWASHER', null);
         $amenityArr[] = $this->set_amenitys('ROOM_STOVETOP', null); 
         $amenityArr[] = $this->set_amenitys('ROOM_TV_SERVICE', "CABLE");
         $amenityArr[] = $this->set_amenitys('ROOM_TV', "LED");
         $amenityArr[] = $this->set_amenitys('ROOM_HYPO_BED_AVAIL', null);
         $amenityArr[] = $this->set_amenitys('ROOM_IRON', "IN_ROOM");
         $amenityArr[] = $this->set_amenitys('ROOM_MP3_PLAYER_DOCK', "IN_ROOM");
         $amenityArr[] = $this->set_amenitys('ROOM_LINENS_PROVIDED', null);
         $amenityArr[] = $this->set_amenitys('ROOM_HOUSEKEEPING', "WEEKLY"); 
         
         
         if( $row['ResourceOfferBathroom1'] == 'jacuzzibath' || $row['ResourceOfferBathroom2'] == 'jacuzzibath' ) {
            $amenityArr[] = $this->set_amenitys('ROOM_PRIVATE_SPA', null);
         }
         
         if( strpos("*".$row['ResourceOfferAmenities'], 'bidet') ){
              $amenityArr[] = $this->set_amenitys('ROOM_BIDET', null);
         }
         
         if( strpos("*".$row['ResourceOfferAmenities'], 'balcony') ){
             $amenityArr[] = $this->set_amenitys('ROOM_BALCONY', "BALCONY");
         }
         
         if( !empty($row['ResourceOfferPhone']) ) {
            $amenityArr[] = $this->set_amenitys('ROOM_PHONE', null);
         }
         
         if( $row['ResourceOfferLift'] == 'Y' )
         {
            //$amenityArr[] = $this->set_amenitys('ELEVATOR', null);
         } 
         if( !empty( $row['ResourceOfferInternet']) )
         {
            if($row['ResourceOfferInternet'] == 'Wi-Fi')
            {
               $amenityArr[] = $this->set_amenitys('ROOM_WIFI_INTERNET', "FREE");
            }
            else
            {
               $amenityArr[] = $this->set_amenitys('ROOM_WIRED_INTERNET', "FREE");  
            }
         }
         if( $row['ResourceOfferCondition'] == 'Y' )
         {
            $amenityArr[] = $this->set_amenitys('ROOM_AIR_CONDITIONING',null);
         }
         if( $row['ResourceOfferMicrowave'] == 'Y' )
         {
            $amenityArr[] = $this->set_amenitys('ROOM_MICROWAVE', "INROOM");
         }
         if( $row['ResourceOfferRefrigerator'] == 'Y' )
         {
            $amenityArr[] = $this->set_amenitys('ROOM_REFRIGERATOR', "INROOM");
         }
         if( $row['ResourceOfferDvdplayer'] == 'Y' )
         {
            $amenityArr[] = $this->set_amenitys('ROOM_DVD_PLAYER', null);
         }
         
         $prop['amenitys'] = $amenityArr;
         $prop['week_discount'] = 0;
         $valIintegrationAll = $property->get_Data_integrationall($integrationAll);
         if( !empty($valIintegrationAll) )
         {
            $prop['CheckInFrom'] = $valIintegrationAll['integrationall']['IntegrationAllCheckInFrom'];
            $prop['CheckInTo'] = $valIintegrationAll['integrationall']['IntegrationAllCheckOutUntil'];
            
            $countWeekDiscount = count( $valIintegrationAll['integrationall']['IntegrationAllLongStays'] ) -1; 
            for($i=0; $i<= $countWeekDiscount; $i++ )
            {
               if( $valIintegrationAll['integrationall']['IntegrationAllLongStays'][$i] <= 7 && $valIintegrationAll['integrationall']['IntegrationAllLongStays1'][$i] >=7)
               {
                  $prop['week_discount'] = $valIintegrationAll['integrationall']['IntegrationAllLongStays2'][$i];
               }
               else if( $valIintegrationAll['integrationall']['IntegrationAllLongStays'][$i] <= 28 && $valIintegrationAll['integrationall']['IntegrationAllLongStays1'][$i] >=28 )
               {
                  $prop['month_discount'] = $valIintegrationAll['integrationall']['IntegrationAllLongStays2'][$i];
               }
            }
            /*
            $IntegrationAllListAmenities = $valIintegrationAll['integrationall']['IntegrationAllListAmenities'];
            foreach($IntegrationAllListAmenities as $rowAmenities)
            {
               if($rowAmenities == 89)
               {
                  $amenityArr[] = $this->set_amenitys('balcony');
                  break;
               }
            }
            */
         }         
      
         $prcent = $manage_db[0]['expediaPercent'];
         $SummPricePrcent = $row['ResourceOfferUAH'] + ( ( $row['ResourceOfferUAH'] /100 ) * $prcent );
         
         $prcent1 = $manage_db[0]['expediaCanelPercent'];
         if(!empty($prcent1))
         {
            $SummPricePrcent = $SummPricePrcent + ( ( $SummPricePrcent /100 ) * $prcent1 );
         }
         
         $prop['price'] = $SummPricePrcent;
         $weekprice =  ( $SummPricePrcent +  ( $SummPricePrcent / 100 ) * $row['ResourceOfferPrcentWeekend'] );
         $prop['weekprice'] = $weekprice;
          
      } 
      /*
      echo"<pre>";
      print_r($prop);
      echo"</pre>";
      */
      return $nineflatjson->set_apartments($prop);
   }
   
//-----------------------------------------------------------------------Реализация Интеграции-----------------------------------------------------------------------------------------------------------
   
   function set_rate_planexml()
   {
       $expediajson = $this->_expediajson;
       $propertyId = 21333450 ;
       $roomTypeId = 220845799;
       $result = $expediajson->json_create_new_rate_plan($propertyId, $roomTypeId, $expediajson->set_rate_plane($prop));
              print_r($result);
       return $result;
   }
   function set_rate_plane1()
   {
       $expediajson = $this->_expediajson;
       $propertyId = 17104939;
       $roomTypeId = 220821577;
       $result = $expediajson->json_read_new_rate_plan();
       return $result;
   }
   
   function booking1()
   {
       $expediajson = $this->_expediajson;
       $result = $expediajson->set_xml_booking_send($expediajson->set_xml_booking());
       return $result;
   }
   
   function booking($HotelID)
   {
       $expediajson = $this->_expediajson;
       $result = $expediajson->set_xml_booking_send($expediajson->set_xml_booking_wait($HotelID));
       return $result;
   }
   
   
   
   
   /*--------------------Заливаем сезоны, открываем даты, закрываем даты для всех квратир на 6 месяцев---------------------------------- ИНИЕГРАЦИЯ ПО КНОПКЕ--------------*/
   function set_CalendarIntegration($ResourceOfferID)
   {
       $DS = &$this->_DS;
       $sql = "SELECT * FROM resourceoffer WHERE  ResourceOfferID = ".$ResourceOfferID;
       $dataOffersRow = $DS->query($sql);
       
       if( !empty($ResourceOfferID) )
       {
          
          $int = $this->expedia_id_relativity_action($ResourceOfferID);
          if( !empty($int) )
          {
             $RoomType = $int[0]['expedia_type_room_id'];
             $PoliticPrice = $int[0]['expedia_price_id'];
             $this->set_calendarOpenAll_ExpediaXML($RoomType, $ResourceOfferID, $PoliticPrice);
             if( !empty($dataOffersRow[0]['ResourceOfferPrcentWeekend']) ){
                $this->set_calendarOpenAll_ExpediaXMLWeek($RoomType, $ResourceOfferID, $PoliticPrice);
             }
             $this->set_calendarExpediaSeazoneXML($RoomType, $ResourceOfferID, $PoliticPrice);
             $this->set_calendarCloseAllExpediaXML($RoomType, $ResourceOfferID);
          }
       }
   }
   
   /*--------------------Заливаем сезоны, открываем даты, закрываем даты для всех квратир на 6 месяцев---------------------------------- ИНИЕГРАЦИЯ ПО КРОНУ--------------*/ 
   function set_CalendarIntegrationCron()
   {
      $DS = $this->_DS;
         //$this->_dataIntegration->sqlAp;set_calendarCloseAllExpediaXML()
      $dataOffersRow = $DS->query("SELECT resourceoffer.ResourceOfferID AS ResourceOfferID, resourceoffer.ResourceOfferPrcentWeekend AS ResourceOfferPrcentWeekend
                                    FROM resourceoffer 
                                    LEFT JOIN sourcesdb_expedia_id ON sourcesdb_expedia_id.recourceoffers_id = resourceoffer.ResourceOfferID
                                    WHERE (sourcesdb_expedia_id.expedia_price_id != '' AND sourcesdb_expedia_id.expedia_price_id > 0 AND resourceoffer.PermAll = '1' AND resourceoffer.ResourceofferDone = 1)
                                    GROUP BY resourceoffer.ResourceOfferID"); 
                                    
      foreach($dataOffersRow as $row)
      {
           if( !empty($row['ResourceOfferID']) )
           {
               $ResourceOfferID = $row['ResourceOfferID'];
               $int = $this->expedia_id_relativity($ResourceOfferID); 
               if( !empty($int) )
               {

                  $RoomType = $int[0]['expedia_type_room_id'];
                  $PoliticPrice = $int[0]['expedia_price_id'];
                  $this->set_calendarOpenAll_ExpediaXML($RoomType, $ResourceOfferID, $PoliticPrice);
                  
                  //sleep(7);
                  if( !empty($row['ResourceOfferPrcentWeekend']) ){
                    $this->set_calendarOpenAll_ExpediaXMLWeek($RoomType, $ResourceOfferID, $PoliticPrice);
                  }
                  
                  $this->set_calendarExpediaSeazoneXML($RoomType, $ResourceOfferID, $PoliticPrice);
                  $this->set_calendarCloseAllExpediaXML($RoomType, $ResourceOfferID);
                  
               }
           }
         
      }
   }
   
   
   /*--------------- Закрываем даты персонально по дате ----------------*/
   function set_CalendarIntegrationPersonCloceDate($ResourceOfferID, $dv1, $dv2)
   {
       if( !empty($ResourceOfferID) )
       {
          
          $int = $this->expedia_id_relativity($ResourceOfferID);
          if( !empty($int) )
          {
             $RoomType = $int[0]['expedia_type_room_id'];
             $PoliticPrice = $int[0]['expedia_price_id'];
             $this->set_calendarClosePersonExpediaXML($RoomType, $ResourceOfferID, $dv1, $dv2);
          }
       }
   }
   
   /*------------------ Открываем даты персонально по дате --------------------*/
   function set_CalendarIntegrationPersonOpenDate($ResourceOfferID, $dv1, $dv2)
   {
       global $CORE;
       if( !empty($ResourceOfferID) )
       {
          $int = $this->expedia_id_relativity($ResourceOfferID);
          
          if( !empty($int) )
          {
             $RoomType = $int[0]['expedia_type_room_id'];
             $PoliticPrice = $int[0]['expedia_price_id'];
             $this->set_calendarOpenPersone_ExpediaXML($RoomType, $ResourceOfferID, $PoliticPrice, $dv1, $dv2);
          }
       }
    }
    
    /*------------------ Открываем даты персонально по дате для сезонов --------------------*/
   function set_CalendarIntegrationPersonOpenDateSezon($ResourceOfferIntegrationIDelet, $ResourceOfferID)
   {
       global $CORE;
       if( !empty($ResourceOfferID) )
       {
          $int = $this->expedia_id_relativity($ResourceOfferID);
          
          if( !empty($int) )
          {
             $RoomType = $int[0]['expedia_type_room_id'];
             $PoliticPrice = $int[0]['expedia_price_id'];
             $this->set_calendarOpenPersone_ExpediaSezoneXML($ResourceOfferIntegrationIDelet, $RoomType, $ResourceOfferID, $PoliticPrice);
          }
       }
    }
    
    
    /*-------------------------- Зливаем сезоны персонально по дате --------------------------*/
   function set_CalendarIntegrationPersonSeazone($ResourceOfferID, $OrderApartmentPersonDateID)
   {
       if( !empty($ResourceOfferID) )
       {
          
          $int = $this->expedia_id_relativity($ResourceOfferID);
          if( !empty($int) )
          {
             $RoomType = $int[0]['expedia_type_room_id'];
             $PoliticPrice = $int[0]['expedia_price_id'];
             $this->set_calendarExpediaSeazonePersonXML($RoomType, $ResourceOfferID, $PoliticPrice, $OrderApartmentPersonDateID);
          }
       }
    }
    
   function set_PropertiApiImg($ResourceOfferID, $resourceId)
   {
       $DS = &$this->_DS;
       $property = $this->_dataIntegration;
       $expediajson = $this->_expediajson;
       $prop['resourceId'] = $resourceId;
       $i=0;
       
       $sql = "SELECT resourceofferbigimg.ResourceOfferBigImgSrc as img
               FROM resourceoffer
               LEFT JOIN resourceofferbigimg ON resourceofferbigimg.ResourceOfferBigImgIDresource = resourceoffer.ResourceOfferID 
               WHERE ResourceOfferID = ".$ResourceOfferID;
       $dataOffersRow = $DS->query($sql);
       $prop['categoryCode'] = 'GUESTROOM_VIEW';
       foreach($dataOffersRow as $row)
       {
          $i++;
          $prop['img'] = 'https://apartments.com.ua/content/'.$row['img'];
          $expediajson->json_create_img($expediajson->set_uploadImgResource($prop));
          if($i>0) break; 
       }
       

   }
   
    
   /*--------------------------------------------------------------------------------------------------Product API Создаем квартиру и удбства----------------------------------------*/
   function set_PropertiApi($ResourceOfferID)
   {
       global $CORE;
       
       $DS = &$this->_DS;
       $property = $this->_dataIntegration;
       $expediajson = $this->_expediajson;
       $CORE->getValue($row[$name],$lang);
       
       $sql = "SELECT resourceoffer.*, 
               region.RegionName as RegionName, 
               region.RegionPosition as RegionPosition
               FROM resourceoffer
               LEFT JOIN region ON region.RegionID = resourceoffer.ResourceOfferAddress 
               WHERE ResourceOfferID = ".$ResourceOfferID;
                      
       $dataOffersRow = $DS->query($sql);
       $propertyId = $expediajson->get_Hotel();
       
       if(!empty($dataOffersRow))
       {
            $nameAp = $dataOffersRow[0]['ResourceOfferAlias']." - ".$dataOffersRow[0]['ResourceOfferBuildingNumber']." ".$this->_controller->getValue($dataOffersRow[0]['RegionName'],'en');
            $prop['nameAp'] = $nameAp;
            $prop['ResourceOfferID'] = $dataOffersRow[0]['ResourceOfferID'];
            $prop['ResourceOfferBedrooms'] = $dataOffersRow[0]['ResourceOfferBedrooms'];
            $prop['ResourceName'] = 'Kiev Apartments. '.$dataOffersRow[0]['ResourceOfferRooms'].' BR '.$CORE->getValue($dataOffersRow[0]['RegionName'],'en');
            $prop['address'] = $CORE->getValue($dataOffersRow[0]['RegionName'],'en');
            $prop['ResourceOfferSquare'] = $dataOffersRow[0]['ResourceOfferSquare'];
            $prop['ResourceOfferSquareFoot'] = round($dataOffersRow[0]['ResourceOfferSquare'] * 0.3048);
            $prop['ResourceOffernumberOfBeds'] = $dataOffersRow[0]['ResourceOffernumberOfBeds'];
            $prop['ResourceOfferRooms'] = $dataOffersRow[0]['ResourceOfferRooms'];
            $prop['typeOfRoom'] = 'Apartment';
            if($dataOffersRow[0]['ResourceOfferStudio'] == 'Y'){
                //$prop['typeOfRoom'] = 'Studio';
            }
            
            $prop['ResourceOfferBedroomsNum'] =  $dataOffersRow[0]['ResourceOfferBedrooms'] + 1;
            
            $prop['children'] = ceil(($prop['ResourceOffernumberOfBeds'] / 2) - 1);
            if($prop['children'] < 1 )
            {
              $prop['children'] = 1; 
            }
            $prop['children'] = 2;

            $prop['roomClass'] = $this->roomClass($dataOffersRow[0]['ResourceOfferClass']);
            
            $countBedroms = 0;
            if( !empty($dataOffersRow[0]['ResourceOfferDoublebeds']) ){
                $countBedroms = $dataOffersRow[0]['ResourceOfferDoublebeds'];
            }
            if( !empty($dataOffersRow[0]['ResourceOfferSinglebeds']) ){
                $countBedroms = $countBedroms + $dataOffersRow[0]['ResourceOfferSinglebeds'];
            }            
            
            $ResourceOfferBathroom = 0;
            if(  $dataOffersRow[0]['ResourceOfferBathroom1'] ) { $ResourceOfferBathroom++; }
            if(  $dataOffersRow[0]['ResourceOfferBathroom2'] ) { $ResourceOfferBathroom++; }  
            $prop['beds'] = $ResourceOfferBathroom;
            if( $prop['beds'] == 0 ){$prop['beds'] = 1;}
            
            $amenties[] = $prop['beds']."Bathrooms";
            $amenties[] = "Balcony";
            $amenties[] = "Bathtub";
            $amenties[] = "Kitchen";
            $amenties[] = "Refrigerator";
            if( $dataOffersRow[0]['ResourceOfferBathroom1'] == 'jacuzzibath' || $dataOffersRow[0]['ResourceOfferBathroom2'] == 'jacuzzibath' ) {
               $amenties[] = 'Hot Tub';
            }
            if( $dataOffersRow[0]['ResourceOfferMicrowave'] == 'Y') {
               $amenties[] = 'Microwave';
            }
            
            $expedia_id = $this->expedia_id_relativity($ResourceOfferID);
            $resourceId = $expedia_id[0]['expedia_type_room_id'];
            
            $json_data = $expediajson->create_room($prop); 

            if(!empty($json_data) && empty($resourceId)) 
            {
                 $result_room = $expediajson->json_create_room($propertyId, $json_data); 
                 $resourceId = $result_room->entity->resourceId;

                 if(!empty($resourceId))
                 {
                    $json_data_amenity = $this->get_amenity($dataOffersRow[0]);
                    $expediajson->json_create_amenity($propertyId, $resourceId, $json_data_amenity);
                    $proPlan['ResourceOfferUAH'] = $dataOffersRow[0]['ResourceOfferUAH'];
                    $proPlan['name_rateplan'] = $nameAp;
                    $proPlan['partnerCode'] = "AP-".$dataOffersRow[0]['ResourceOfferAlias'];
                    $proPlan['ResourceOfferBedrooms'] = $prop['ResourceOfferBedrooms'];

                    $rateplan = $expediajson->json_create_new_rate_plan($propertyId, $resourceId, $expediajson->set_rate_plane($proPlan));
                    
                    //$resourceId_save = $rateplan->entity->resourceId;
                    //$Aio = $rateplane->entity->distributionRules[0]->expediaId;
                    //$Dio = $rateplane->entity->distributionRules[1]->expediaId;
                    $this->save_idroom_idpolise($ResourceOfferID, $resourceId, $resourceId_save);
                    
                 }
                 
            }
            if(!empty($json_data) && !empty($resourceId))
            {
                 $json_data = $expediajson->create_update_room($prop, $resourceId);
                 $result_room = $expediajson->json_update_room($propertyId, $resourceId, $json_data);
                 $json_data_amenity = $this->get_amenity($dataOffersRow[0]); 
                 $expediajson->json_create_amenity($propertyId, $resourceId, $json_data_amenity);
            }     
       }
   }
   
   
   function get_amenity($row)
   {   
         $ResourceOfferBathroom = 0;
         if(  $dataOffersRow[0]['ResourceOfferBathroom1'] ) { $ResourceOfferBathroom++; }
         if(  $dataOffersRow[0]['ResourceOfferBathroom2'] ) { $ResourceOfferBathroom++; }  
         if( !empty( $row['ResourceOfferDoublebeds']) ){ $prop['ResourceOfferDoublebeds'] =  $row['ResourceOfferDoublebeds']; }
         if( !empty( $row['ResourceOfferSinglebeds']) ){ $prop['ResourceOfferSinglebeds'] =  $row['ResourceOfferSinglebeds']; }
         
         if( !empty( $row['BedroomAdition']) )
         { 
            if( strpos( $row['BedroomAdition'], 'singlesofa'))
            {
               $prop['singlesofa']++;
               //$amenityArr[] = $this->set_amenitys('ROOM_SOFA_BED', "SOFA_BED"); 
            } 
            if( strpos( $row['BedroomAdition'], 'two-single-sofa'))
            {
               $prop['singlesofa'] = $prop['singlesofa'] + 2;
            } 
            
            if( strpos( $row['BedroomAdition'], 'doublesofa'))
            {
               $prop['doublesofa']++;
            } 
            if( strpos( $row['BedroomAdition'], 'two-double-sofa'))
            {
               $prop['doublesofa'] = $prop['doublesofa'] + 2;
            } 
         }

         //$amenityArr[] = $this->set_amenitys('ACCESSIBLE_BATHROOM', null);
         if(!empty($ResourceOfferBathroom))
         {
            $amenityArr[] = $this->set_amenitys('ROOM_SECOND_BATHROOM', null);
         }
         
         
         if( $arr['ResourceOfferLivingRoom'] > 0 ){
            $amenityArr[] = array("code" =>'ROOM_LIVING_ROOM', "value" => $arr['ResourceOfferLivingRoom']);
         }
         
         if( !empty( $row['ResourceOfferBathroom1']) ){
            $roomcount++;
         }
         if( !empty( $row['ResourceOfferBathroom2']) ){
            $roomcount++;
         }
         
         $amenityArr[] = array("code" => 'ROOM_NUMBER_OF_BATHROOMS', "value" => $roomcount);
         $amenityArr[] = array("code" => 'ROOM_NUMBER_OF_SEPARATE_BEDROOMS', "value" => $row['ResourceOfferBedrooms']);
         $amenityArr[] = $this->set_amenitys('ROOM_STOVETOP', null);
         $amenityArr[] = $this->set_amenitys('ROOM_CLUB_EXEC_SEPARATE_CHECKIN', null);
         $amenityArr[] = $this->set_amenitys('ROOM_BATHROOM_TYPE', "PRIVATE_BATHROOM");
         $amenityArr[] = $this->set_amenitys('ROOM_FREE_TOILETRIES', null);
         $amenityArr[] = $this->set_amenitys('ROOM_KITCHEN', "KITCHEN");
         $amenityArr[] = $this->set_amenitys('ROOM_TV_SERVICE', "CABLE");
         $amenityArr[] = $this->set_amenitys('ROOM_TV', "FLAT_PANEL");
         $amenityArr[] = $this->set_amenitys('ROOM_IRON', "IN_ROOM");
         $amenityArr[] = $this->set_amenitys('ROOM_BATHTUB_TYPE', "DEEP_SOAKING");
         $amenityArr[] = $this->set_amenitys('ROOM_LINENS_PROVIDED', null);
         $amenityArr[] = $this->set_amenitys('ROOM_HOUSEKEEPING', "WEEKLY"); 
         $amenityArr[] = $this->set_amenitys('ROOM_BLACKOUT_DRAPES', null);
         $amenityArr[] = $this->set_amenitys('ROOM_DECOR', null);
         $amenityArr[] = $this->set_amenitys('ROOM_FURNISHING', null);
         $amenityArr[] = $this->set_amenitys('ROOM_WASHER', null);
         $amenityArr[] = $this->set_amenitys('ROOM_SOUND_ISOLATION', "SOUNDPROOFED");
         $amenityArr[] = $this->set_amenitys('ROOM_HAIR_DRYER', "IN_ROOM");
         
         
         if($row['ResourceOfferB2Plus1'] == 'withshower'){
            $amenityArr[] = $this->set_amenitys('ROOM_SHOWER_TYPE', "SHOWER_ONLY");
         }
         else{
             $amenityArr[] = $this->set_amenitys('ROOM_SHOWER_TYPE', "BATHTUB_OR_SHOWER");
         }
         
         if( $row['ResourceOfferBathroom1'] =='jacuzzibath' || $row['ResourceOfferBathroom2']== 'jacuzzibath' ) {
            $amenityArr[] = $this->set_amenitys('ROOM_PRIVATE_SPA', null);
         }
         
         if( strpos("*".$row['ResourceOfferAmenities'], 'bidet') ){
              $amenityArr[] = $this->set_amenitys('ROOM_BIDET', null);
         }
         
         if( strpos("*".$row['ResourceOfferAmenities'], 'balcony') ){
             $amenityArr[] = $this->set_amenitys('ROOM_BALCONY', "BALCONY");
         }
         
         
         if( !empty($row['ResourceOfferPhone']) ) 
         {
            $amenityArr[] = $this->set_amenitys('ROOM_PHONE', null);
         }
         if( $row['ResourceOfferLift'] == 'Y' )
         {
            //$amenityArr[] = $this->set_amenitys('ELEVATOR', null);
         } 
         if( !empty( $row['ResourceOfferInternet']) )
         {
            if($row['ResourceOfferInternet'] == 'Wi-Fi')
            {
               $amenityArr[] = $this->set_amenitys('ROOM_WIFI_INTERNET', "FREE");
            }
            else
            {
               $amenityArr[] = $this->set_amenitys('ROOM_WIRED_INTERNET', "FREE");  
            }
         }
         if( $row['ResourceOfferCondition'] == 'Y' )
         {
            $amenityArr[] = $this->set_amenitys('ROOM_AIR_CONDITIONING', null);
         }
         if( $row['ResourceOfferMicrowave'] == 'Y' )
         {
            $amenityArr[] = $this->set_amenitys('ROOM_MICROWAVE', "IN_ROOM");
         }
         if( $row['ResourceOfferRefrigerator'] == 'Y' )
         {
            $amenityArr[] = $this->set_amenitys('ROOM_REFRIGERATOR', "IN_ROOM");
         }
         if( $row['ResourceOfferDvdplayer'] == 'Y' )
         {
            $amenityArr[] = $this->set_amenitys('ROOM_DVD_PLAYER', null);
         }
         
         return $amenityArr;
   } 
   /*--------------------------------------------------------------------END ---------------------------------------------------------------------*/
   
   
   /*------------------ Action Ret Plan---------*/
   function set_action_deaction_ratePlan($ResourceOfferID, $prop){
       $this->action_deaction_ratePlan($ResourceOfferID, $prop);
   }
   
    /* подтверждение заказа */
   function set_bookingConfirm($id, $type)
   {
      $expediajson = $this->_expediajson;
      $arr = $this->listconfirmListID($id);
      if(!empty($arr))
      {
         $result = $expediajson->set_xml_booking_confirm($expediajson->set_xml_confirmBooking($arr, $type));
      }
      
      return $result;
   }
   
   function set_CloseAvailable($ResourceOfferID)
   {
       if( !empty($ResourceOfferID) )
       {
          
          $int = $this->expedia_id_relativity($ResourceOfferID);
          if( !empty($int) )
          {
             $RoomType = $int[0]['expedia_type_room_id'];
             $PoliticPrice = $int[0]['expedia_price_id'];
             //$this->set_calendarOpenAll_ExpediaXML($RoomType, $ResourceOfferID, $PoliticPrice);
             //$this->set_calendarExpediaSeazoneXML($RoomType, $ResourceOfferID, $PoliticPrice);
             $this->set_calendarCloseAllExpediaXML($RoomType, $ResourceOfferID);
          }
       }
   }
   
   
//-----------------------------------------------------------------------Реализация Методов-----------------------------------------------------------------------------------------------------------
   
   
   
   
   /*----------- активация деактивация политики отмены ---------*/
   function action_deaction_ratePlan($ResourceOfferID, $prop){ 
       $DS = &$this->_DS;
       $expediajson = $this->_expediajson;
       $info = $this->get_idroom_idpolise($ResourceOfferID);
       $HotelID = $this->set_hotelid($ResourceOfferID);
       if(!empty($info))
       {
            $roomTypeId = $info['roomtype'];
            $ratePlans = $info['pricitype'];
            $result = $expediajson->json_activate_deactivate($ratePlans, $roomTypeId, $expediajson->set_active_deactive_cancelation_pokice($prop), $HotelID);
       }
       
   }
   
   
   function get_idroom_idpolise($ResourceOfferID)
   {
      $DS = &$this->_DS;
      $sql = "SELECT expedia_type_room_id as roomtype, expedia_price_id as pricitype, action
               FROM sourcesdb_expedia_id
               WHERE recourceoffers_id = ".$ResourceOfferID;
       $dataOffersRow = $DS->query($sql);
       foreach($dataOffersRow as $row){
          $prop['roomtype'] = $row['roomtype'];
          $prop['pricitype'] = $row['pricitype'];
          $prop['action'] = $row['action'];
       }
       
       return $prop;
   }
   
   /*--------------сохранение id номер и ------------------------*/
   function save_idroom_idpolise($ResourceOfferID, $resourceId, $plainId)
   {
        if( !empty($resourceId) && !empty($ResourceOfferID) ) 
        {
           $relativity = $this->expedia_id_relativity($ResourceOfferID);
           if(!empty($relativity))
           {
               $expedia_type_room_id = $input['expedia_type_room_id'];
               $expedia_price_id  = $input['expedia_price_id'];
               $DS->query("UPDATE sourcesdb_expedia_id SET `expedia_type_room_id` = '$resourceId', expedia_price_id = '$plainId' WHERE `recourceoffers_id`=".$ResourceOfferID);
           }
           else
           {
              $table = 'sourcesdb_expedia_id';
              $inputExpedia['sourcesdb_expedia_id'.DTR.'expedia_type_room_id'] = $resourceId;
              $inputExpedia['sourcesdb_expedia_id'.DTR.'expedia_price_id'] = $plainId;
              $inputExpedia['sourcesdb_expedia_id'.DTR.'recourceoffers_id'] = $ResourceOfferID;
              $inputExpedia['sourcesdb_expedia_id'.DTR.'action'] = 1;
              $DS->insert($inputExpedia, $table);
           }
        }
   }
   
   
   function roomClass($type)
   {
      switch($type)
      {
          case 'VIP':
              $res = "Elite";   
              break;
          case 'luxevip':
              $res = "Luxury";
              break;
          case 'business':
              $res = "Standard";
              break;
          case 'Economy':
              $res = "Economy";
              break;
          case 'soviet':
              $res = "Basic";
              break;
          default:
              $res = "Basic";
              break;
      }
      
      return $res;
      
   }
   
   function expedia_id_relativity($ResourceOfferID)
   {
      $DS = &$this->_DS;
      $filter='';
      if(!empty($ResourceOfferID))
      {
         $filter="WHERE recourceoffers_id = '".$ResourceOfferID."'";
      }
      return $DS->query("SELECT * FROM sourcesdb_expedia_id ".$filter);
   }
   
   function expedia_id_relativity_action($ResourceOfferID)
   {
      $DS = &$this->_DS;
      $filter='';
      if(!empty($ResourceOfferID))
      {
         $filter="WHERE action = 1 AND recourceoffers_id = '".$ResourceOfferID."'";
      }
      return $DS->query("SELECT * FROM sourcesdb_expedia_id ".$filter);
   }
   
   function set_photo($ResourceOfferID)
   {
      $DS = &$this->_DS;
      $photoApartmen = $DS->query("SELECT * FROM resourceofferbigimg 
                                   LEFT JOIN typeimageintegration ON typeimageintegration.TypeImageIntegrationID = resourceofferbigimg.TypeImageIntegration
                                   WHERE resourceofferbigimg.ResourceOfferBigImgIDresource = '".$ResourceOfferID."' ORDER BY TypeImageIntegration ASC");
                                   
      $counti = count( $photoApartmen ) -1;
      $src='https://apartments.com.ua/content/';
      for($i=0; $i<=$counti; $i++)       
      {             
        $place_photo[] = array(
                          "url" => 'https://apartments.com.ua/content/'.$photoApartmen[$i]['ResourceOfferBigImgSrc'],
                          "categoryCode" =>"FEATURED_IMAGE",
                          "caption" =>"Main Image");
      }
      
      return $place_photo;
   }
   
   function get_managerExpedia()
   {
      $DS = &$this->_DS;
      $manage_db = $DS->query('SELECT * FROM sourcesdb_expedia
                         LEFT JOIN optioninegrationadmin ON optioninegrationadmin.InegrationAdminID = sourcesdb_expedia.expediaManager 
                         ORDER BY sourcesdb_expedia.expediaID DESC LIMIT 1');
      return $manage_db;                  
   }
   
   function set_amenitys($name, $type)
   {
      if(!empty($type) || $type != null){
         return array("code" => $name, "detailCode" => $type);
      }
      else{
         return array("code" => $name);
      }
   }
   
   function set_openDateCalendar($ResourceOfferID, $dv)
   {
      $DS = &$this->_DS;
      $res = $DS->query("SELECT * FROM icalendar_resource
                         LEFT JOIN orderapartmentpersondate ON orderapartmentpersondate.OrderApartmentPersonDateIDResourseOfferID = icalendar_resource.icalendar_resource_id_resource
                         WHERE 
                         (
                         (icalendar_resource.icalendar_resource_id_resource = '".$ResourceOfferID."' AND icalendar_resource.dtstart <= '".$dv."' AND icalendar_resource.dtend >= '".$dv."' )
                         OR 
                         (orderapartmentpersondate.OrderApartmentPersonDateIDResourseOfferID = '".$ResourceOfferID."' AND orderapartmentpersondate.OrderApartmentPersonDateTypeOrder = 2 AND orderapartmentpersondate.OrderApartmentPersonDateStart <= '".$dv."' AND orderapartmentpersondate.OrderApartmentPersonDateEnd >= '".$dv."') 
                         )");
   }
   

   /* закрытие дат всех */
   function set_calendarCloseAllExpediaXML($RoomType, $ResourceOfferID)
   {

      $DS = &$this->_DS; 
      $property = $this->_dataIntegration;
      $expediajson = $this->_expediajson;
      $manage_db = $this->get_managerExpedia();
   
      if( !empty($ResourceOfferID) )
      { 
         $HotelID = $this->set_hotelid($ResourceOfferID);
         $sql = "SELECT * FROM resourceoffer
                WHERE resourceoffer.PermAll = '1' AND resourceoffer.ResourceofferDone = 1 AND resourceoffer.ResourceOfferID = ".$ResourceOfferID; 
   
         $dataOffersRow = $DS->query($sql); 
         foreach($dataOffersRow as $row)
         {
             $prop['minimum_nights'] = $property->get_minDay( 'expediaMinDay', 'sourcesdb_expedia', $ResourceOfferID );
             $prcent = $manage_db[0]['expediaPercent'];
             $SummPricePrcent = $row['ResourceOfferUAH'] + ( ( $row['ResourceOfferUAH'] /100 ) * $prcent );
             $prcent1 = $manage_db[0]['expediaCanelPercent'];
             if(!empty($prcent1))
             {
               $SummPricePrcent = $SummPricePrcent + ( ( $SummPricePrcent /100 ) * $prcent1 );
             }
         
             $prop['price'] = $SummPricePrcent;
             $weekprice =  ( $SummPricePrcent +  ( $SummPricePrcent / 100 ) * $row['ResourceOfferPrcentWeekend'] );
             $prop['weekprice'] = $weekprice;
             $xml .= $this->set_calendarAllExpedia(date('Y-m-d'), $dv2, $ResourceOfferID, $prcent, $prcent1, $RoomType,  $prop['minimum_nights']);
         }
      } 
      if(!empty($xml))
      {
         $xmlSent = $expediajson->set_xml_available($xml, $HotelID);
         $xmlSent;
         return $expediajson->set_xml_available_send($ResourceOfferID, $xmlSent);
      }
      else
      {
         return false;
      }
      
   }
   
   /* закрытие дат персонально */
   function set_calendarClosePersonExpediaXML($RoomType, $ResourceOfferID, $dv1, $dv2)
   {

      $DS = &$this->_DS; 
      $property = $this->_dataIntegration;
      $expediajson = $this->_expediajson;
      $manage_db = $this->get_managerExpedia();
      $HotelID = $this->set_hotelid($ResourceOfferID);
   
      if( !empty($ResourceOfferID) )
      { 
         $sql = "SELECT * FROM resourceoffer
                WHERE resourceoffer.PermAll = '1' AND resourceoffer.ResourceofferDone = 1 AND resourceoffer.ResourceOfferID = ".$ResourceOfferID;
   
         $dataOffersRow = $DS->query($sql); 
         foreach($dataOffersRow as $row)
         {
             $prop['minimum_nights'] = $property->get_minDay( 'expediaMinDay', 'sourcesdb_expedia', $ResourceOfferID );
             $prcent = $manage_db[0]['expediaPercent'];
             $SummPricePrcent = $row['ResourceOfferUAH'] + ( ( $row['ResourceOfferUAH'] /100 ) * $prcent );
             $prcent1 = $manage_db[0]['expediaCanelPercent'];
             if(!empty($prcent1))
             {
               $SummPricePrcent = $SummPricePrcent + ( ( $SummPricePrcent /100 ) * $prcent1 );
             }
         
             $prop['price'] = $SummPricePrcent;
             $weekprice =  ( $SummPricePrcent +  ( $SummPricePrcent / 100 ) * $row['ResourceOfferPrcentWeekend'] );
             $prop['weekprice'] = $weekprice;
             $xml .= $this->set_calendarPersonExpedia($dv1, $dv2, $ResourceOfferID, $prcent, $prcent1, $RoomType,  $prop['minimum_nights']);
         }
      } 
      if(!empty($xml))
      {
         $xmlSent = $expediajson->set_xml_available($xml, $HotelID);
         $xmlSent;
         return $expediajson->set_xml_available_send($ResourceOfferID, $xmlSent);
      }
      else
      {
         return false;
      }
      
   }
   
   /* интеграция сезонов персонально */
   function set_calendarExpediaSeazonePersonXML($RoomType, $ResourceOfferID, $PoliticPrice, $OrderApartmentPersonDateID)
   {

      $DS = &$this->_DS; 
      $property = $this->_dataIntegration;
      $expediajson = $this->_expediajson;
      $manage_db = $this->get_managerExpedia();
      if( !empty($ResourceOfferID) )
      { 
         $sql = "SELECT * FROM resourceoffer
                WHERE resourceoffer.PermAll = '1' AND resourceoffer.ResourceofferDone = 1 AND resourceoffer.ResourceOfferID = ".$ResourceOfferID; 
   
         $dataOffersRow = $DS->query($sql); 
         foreach($dataOffersRow as $row)
         {
             $prop['minimum_nights'] = $property->get_minDay( 'expediaMinDay', 'sourcesdb_expedia', $ResourceOfferID );
             $prcent = $manage_db[0]['expediaPercent'];
             $SummPricePrcent = $row['ResourceOfferUAH'] + ( ( $row['ResourceOfferUAH'] /100 ) * $prcent );
             $prcent1 = $manage_db[0]['expediaCanelPercent'];
             if(!empty($prcent1))
             {
               $SummPricePrcent = $SummPricePrcent + ( ( $SummPricePrcent /100 ) * $prcent1 );
             }
         
             $prop['price'] = $SummPricePrcent;
             $weekprice =  ( $SummPricePrcent +  ( $SummPricePrcent / 100 ) * $row['ResourceOfferPrcentWeekend'] );
             $prop['weekprice'] = $weekprice;
             $this->set_calendarAllExpediaSeazonePerson($OrderApartmentPersonDateID, $prcent, $prcent1, $RoomType, $prop['minimum_nights'], $PoliticPrice);
         }
      } 
      
   }

   /* интеграция сезонов всех */
   function set_calendarExpediaSeazoneXML($RoomType, $ResourceOfferID, $PoliticPrice)
   {

      $DS = &$this->_DS; 
      $property = $this->_dataIntegration;
      $expediajson = $this->_expediajson;
      $manage_db = $this->get_managerExpedia();
   
      if( !empty($ResourceOfferID) )
      { 
         $sql = "SELECT * FROM resourceoffer
                WHERE resourceoffer.PermAll = '1' AND resourceoffer.ResourceofferDone = 1 AND resourceoffer.ResourceOfferID = ".$ResourceOfferID;//resourceoffer.PermAll = '1' AND resourceoffer.ResourceOfferIntegration = '1' AND  
   
         $dataOffersRow = $DS->query($sql); 
         foreach($dataOffersRow as $row)
         {
             $prop['minimum_nights'] = $property->get_minDay( 'expediaMinDay', 'sourcesdb_expedia', $ResourceOfferID );
             $prcent = $manage_db[0]['expediaPercent'];
             $SummPricePrcent = $row['ResourceOfferUAH'] + ( ( $row['ResourceOfferUAH'] /100 ) * $prcent );
             $prcent1 = $manage_db[0]['expediaCanelPercent'];
             if(!empty($prcent1))
             {
               $SummPricePrcent = $SummPricePrcent + ( ( $SummPricePrcent /100 ) * $prcent1 );
             }
         
             $prop['price'] = $SummPricePrcent;
             $weekprice =  ( $SummPricePrcent +  ( $SummPricePrcent / 100 ) * $row['ResourceOfferPrcentWeekend'] );
             $prop['weekprice'] = $weekprice;
             $this->set_calendarAllExpediaSeazone(date('Y-m-d'), $dv2, $ResourceOfferID, $prcent, $prcent1, $RoomType,  $prop['minimum_nights'], $PoliticPrice);
         }
      } 
      
   }
   
   /* открытие дат всех */
   function set_calendarOpenAll_ExpediaXML($RoomType, $ResourceOfferID, $PoliticPrice)
   {

      $DS = &$this->_DS; 
      $property = $this->_dataIntegration;
      $expediajson = $this->_expediajson;
      $manage_db = $this->get_managerExpedia();
      
      if( !empty($ResourceOfferID) )
      { 
         $HotelID = $this->set_hotelid($ResourceOfferID);
         $sql = "SELECT * FROM resourceoffer
                WHERE resourceoffer.PermAll = '1' AND resourceoffer.ResourceofferDone = 1 AND resourceoffer.ResourceOfferID = ".$ResourceOfferID;
                
         $calendar = $property->set_actual_calendar($ResourceOfferID);
         if(!empty($calendar)){
            $this->count_calendar = $calendar;
         }
   
         $dataOffersRow = $DS->query($sql); 
         foreach($dataOffersRow as $row)
         {
             $prop['minimum_nights'] = $property->get_minDayNew('expediaMinDay', 'sourcesdb_expedia', $ResourceOfferID );
             $prcent = $manage_db[0]['expediaPercent'];
             $SummPricePrcent = $row['ResourceOfferUAH'] + ( ( $row['ResourceOfferUAH'] /100 ) * $prcent );
             $prcent1 = $manage_db[0]['expediaCanelPercent'];
             if(!empty($prcent1))
             {
               $SummPricePrcent = $SummPricePrcent + ( ( $SummPricePrcent /100 ) * $prcent1 );
             }
         
             $prop['price'] = $SummPricePrcent;
             $xml = $this->set_CalendarAvailabilityOpen($RoomType, $prop['price'], $this->count_calendar, $prop['minimum_nights'], $PoliticPrice);
         }
      } 
      if(!empty($xml))
      {
         $xmlSent = $expediajson->set_xml_available($xml, $HotelID);
         return $expediajson->set_xml_available_send($ResourceOfferID, $xmlSent);
      }
      else
      {
         return false;
      }
   }
   
      /* открытие дат всех выходные дни */
   function set_calendarOpenAll_ExpediaXMLWeek($RoomType, $ResourceOfferID, $PoliticPrice)
   {
      $DS = &$this->_DS; 
      $property = $this->_dataIntegration;
      $expediajson = $this->_expediajson;
      $manage_db = $this->get_managerExpedia();

      if( !empty($ResourceOfferID) )
      { 
         $HotelID = $this->set_hotelid($ResourceOfferID);
         $sql = "SELECT * FROM resourceoffer
                WHERE resourceoffer.PermAll = '1' AND resourceoffer.ResourceofferDone = 1 AND resourceoffer.ResourceOfferID = ".$ResourceOfferID;
   
         $dataOffersRow = $DS->query($sql); 
         foreach($dataOffersRow as $row)
         {
             $prop['minimum_nights'] = $property->get_minDayNew( 'expediaMinDay', 'sourcesdb_expedia', $ResourceOfferID );
             $prcent = $manage_db[0]['expediaPercent'];
             $SummPricePrcent = $row['ResourceOfferUAH'] + ( ( $row['ResourceOfferUAH'] /100 ) * $prcent );
             $prcent1 = $manage_db[0]['expediaCanelPercent'];
             if(!empty($prcent1))
             {
               $SummPricePrcent = $SummPricePrcent + ( ( $SummPricePrcent /100 ) * $prcent1 );
             }
             $prc = $row['ResourceOfferPrcentWeekend'];
             $prop['price'] = $SummPricePrcent;
             $xml = $this->set_CalendarAvailabilityOpenWeekDay($RoomType, $prop['price'], $this->count_calendar, $prop['minimum_nights'], $PoliticPrice, $prc);
         }
      } 
      if(!empty($xml))
      {
         $xmlSent = $expediajson->set_xml_available($xml, $HotelID);
         return $expediajson->set_xml_available_send($ResourceOfferID, $xmlSent);
      }
      else
      {
         return false;
      }

   }
   
   /* открытие дат персонально */
   function set_calendarOpenPersone_ExpediaXML($RoomType, $ResourceOfferID, $PoliticPrice, $dv1, $dv2)
   {

      $DS = &$this->_DS; 
      $property = $this->_dataIntegration;
      $expediajson = $this->_expediajson;
      $manage_db = $this->get_managerExpedia();
   
      if( !empty($ResourceOfferID) )
      { 
         $HotelID = $this->set_hotelid($ResourceOfferID);
         $sql = "SELECT * FROM resourceoffer
                WHERE resourceoffer.PermAll = '1' AND resourceoffer.ResourceofferDone = 1 AND resourceoffer.ResourceOfferID = ".$ResourceOfferID;
   
         $dataOffersRow = $DS->query($sql); 
         foreach($dataOffersRow as $row)
         {
             $prop['minimum_nights'] = $property->get_minDay( 'expediaMinDay', 'sourcesdb_expedia', $ResourceOfferID );
             $prcent = $manage_db[0]['expediaPercent'];
             $SummPricePrcent = $row['ResourceOfferUAH'] + ( ( $row['ResourceOfferUAH'] /100 ) * $prcent );
             $prcent1 = $manage_db[0]['expediaCanelPercent'];
             if(!empty($prcent1))
             {
               $SummPricePrcent = $SummPricePrcent + ( ( $SummPricePrcent /100 ) * $prcent1 );
             }
         
             $prop['price'] = $SummPricePrcent;
             $xml = $this->set_CalendarAvailabilityOpenPersone($RoomType, $prop['price'], $this->count_calendar, $prop['minimum_nights'], $PoliticPrice, $dv1, $dv2);
         }
      } 
      if(!empty($xml))
      {
         $xmlSent = $expediajson->set_xml_available($xml, $HotelID);
         return $expediajson->set_xml_available_send($ResourceOfferID, $xmlSent);
      }
      else
      {
         return false;
      }
   }
   
      /* открытие дат персонально удаление сезона */
   function set_calendarOpenPersone_ExpediaSezoneXML($ResourceOfferIntegrationIDelet, $RoomType, $ResourceOfferID, $PoliticPrice)
   {

      $DS = &$this->_DS; 
      $property = $this->_dataIntegration;
      $expediajson = $this->_expediajson;
      $manage_db = $this->get_managerExpedia();
   
      if( !empty($ResourceOfferID) )
      { 
         $HotelID = $this->set_hotelid($ResourceOfferID);
         $sql = "SELECT * FROM resourceoffer
                WHERE resourceoffer.PermAll = '1' AND resourceoffer.ResourceofferDone = 1 AND resourceoffer.ResourceOfferID = ".$ResourceOfferID;
   
         $dataOffersRow = $DS->query($sql); 
         foreach($dataOffersRow as $row)
         {
             $prop['minimum_nights'] = $property->get_minDay( 'expediaMinDay', 'sourcesdb_expedia', $ResourceOfferID );
             $prcent = $manage_db[0]['expediaPercent'];
             $SummPricePrcent = $row['ResourceOfferUAH'] + ( ( $row['ResourceOfferUAH'] /100 ) * $prcent );
             $prcent1 = $manage_db[0]['expediaCanelPercent'];
             if(!empty($prcent1))
             {
               $SummPricePrcent = $SummPricePrcent + ( ( $SummPricePrcent /100 ) * $prcent1 );
             }
         
             $prop['price'] = $SummPricePrcent;
             $xml = $this->set_CalendarAvailabilityOpenPersoneSezone($ResourceOfferIntegrationIDelet, $RoomType, $prop['price'], $this->count_calendar, $prop['minimum_nights'], $PoliticPrice);
         }
      } 
      if(!empty($xml))
      {
         $xmlSent = $expediajson->set_xml_available($xml, $HotelID);
         return $expediajson->set_xml_available_send($ResourceOfferID, $xmlSent);
      }
      else
      {
         return false;
      }
   }
   
   /* открываем даты на экспедеа за 6 месяцев */
   function set_CalendarAvailabilityOpen($RoomType, $price, $count_calendar, $DateMinDay, $PoliticPrice)
   {
      global $CORE;

      if(!empty($count_calendar))
      {
         for($j=0; $j<=$count_calendar; $j++)
         {
            $day_str = '';
            $month = date("m", mktime(0,0,0,date('m')+$j,1,date('Y')));
            $year  = date("Y", mktime(0,0,0,date('m')+$j,1,date('Y')));
            $day_end = date("t", mktime(0,0,0,$month,1,$year));

               $day = '01';
               if($j==0)
               {
                  $day = date('d');
               }
               $dateToday = date($year.'-'.$month.'-'.$day);
               $dateToend = date($year.'-'.$month.'-'.$day_end);
               
               $xml.='<AvailRateUpdate>';
               $xml.='<DateRange from="'.$dateToday.'" to="'.$dateToend.'" />'; 
               $xml.='<RoomType id="'.$RoomType.'" closed="false" >';
               $xml.='<Inventory flexibleAllocation="1"/>';
               $xml.='<RatePlan id="'.$PoliticPrice.'" closed="false">';
               $xml.='<Rate currency="UAH">';
               $xml.='<PerDay rate="'.round($price).'" />';
               $xml.='</Rate>';
               $xml.='<Restrictions minLOS="'.$DateMinDay.'" maxLOS="28" closedToArrival="false" closedToDeparture="false" />';
               $xml.='</RatePlan>';
               $xml.='</RoomType>';
               $xml.='</AvailRateUpdate>';

         }
         // closedToArrival="false" closedToDeparture="false"
         return $xml;
      }
   
   }
   
      /* открываем даты на экспедеа за 6 месяцев Устанавливаем цены на выходные дни */
   function set_CalendarAvailabilityOpenWeekDay($RoomType, $price, $count_calendar, $DateMinDay, $PoliticPrice, $prc)
   {
      global $CORE;
      $xml = '';
      if(!empty($count_calendar))
      {
         for($j=0; $j<=$count_calendar; $j++)
         {
            $day_str = '';
            $month = date("m", mktime(0,0,0,date('m')+$j,1,date('Y')));
            $year  = date("Y", mktime(0,0,0,date('m')+$j,1,date('Y')));
            $day_end = date("t", mktime(0,0,0,$month,1,$year));

               $day = '01';
               if($j==0)
               {
                  $day = date('d');
               }
               $dateToday = date($year.'-'.$month.'-'.$day);
               $dateToend = date($year.'-'.$month.'-'.$day_end);
               for($i=$day; $i<=$day_end; $i++)
               {
                   if ( date("w",strtotime($year.'-'.$month.'-'.$i)) == 6 )
                   {
                       $dateWeek_s = date($year.'-'.$month.'-'.str_pad($i, 2, '0', STR_PAD_LEFT));
                       if( $day_end >= $i+1){
                          $iend = $i+1;
                          $dateWeek_e = date($year.'-'.$month.'-'.str_pad($iend, 2, '0', STR_PAD_LEFT));
                        }
                        else{
                            $dateWeek_e = $dateToend;
                        }
                        
                       $price_new = $price;
                       if(!empty($prc)){
                          $price_new = $price + ( ($price/100) * $prc );
                       } 
                       $xml.='<AvailRateUpdate>';
                       $xml.='<DateRange from="'.$dateWeek_s.'" to="'.$dateWeek_e.'" />';
                       $xml.='<RoomType id="'.$RoomType.'" closed="false" >';
                       $xml.='<Inventory flexibleAllocation="1"/>';
                       $xml.='<RatePlan id="'.$PoliticPrice.'" closed="false">';
                       $xml.='<Rate currency="UAH">';
                       $xml.='<PerDay rate="'.round($price_new).'" />';
                       $xml.='</Rate>';
                       $xml.='<Restrictions minLOS="'.$DateMinDay.'" maxLOS="28" closedToArrival="false" closedToDeparture="false" />';
                       $xml.='</RatePlan>';
                       $xml.='</RoomType>';
                       $xml.='</AvailRateUpdate>';
                   }
               }

         }
         // closedToArrival="false" closedToDeparture="false"
         return $xml;
      }
   
   }
   
   /* открываем даты на экспедеа персонально */
   function set_CalendarAvailabilityOpenPersone($RoomType, $price, $count_calendar, $DateMinDay, $PoliticPrice, $dv1, $dv2)
   {
        
        $dateToday = $dv1;
        $dateToend = $dv2;
        //$DateMinDay = 10;
        $maxDay = 28;
        
        
        $xml.='<AvailRateUpdate>';
        $xml.='<DateRange from="'.$dateToday.'" to="'.$dateToend.'" />';
        $xml.='<RoomType id="'.$RoomType.'" closed="false" >';
        $xml.='<Inventory flexibleAllocation="1"/>';
        $xml.='<RatePlan id="'.$PoliticPrice.'" closed="false">';
        $xml.='<Rate currency="UAH">';
        $xml.='<PerDay rate="'.round($price).'" />';
        $xml.='</Rate>';
        $xml.='<Restrictions minLOS="'.$DateMinDay.'" maxLOS="'.$maxDay.'" closedToArrival="false" closedToDeparture="false" />';
        $xml.='</RatePlan>';
        $xml.='</RoomType>';
        $xml.='</AvailRateUpdate>';

        return $xml;
   }
   
   /* открываем даты на экспедеа персонально для сезонов */
   function set_CalendarAvailabilityOpenPersoneSezone($ResourceOfferIntegrationIDelet, $RoomType, $price, $count_calendar, $DateMinDay, $PoliticPrice)
   {
        $DS = &$this->_DS;
        $property = $this->_dataIntegration;
        $calendar3 =  $property->set_seazone_id($ResourceOfferIntegrationIDelet);
        
        $dateToday = $calendar3[0]['OrderApartmentPersonDateStart'];
        $dateToend = $calendar3[0]['OrderApartmentPersonDateEnd'];
        
        $xml.='<AvailRateUpdate>';
        $xml.='<DateRange from="'.$dateToday.'" to="'.$dateToend.'" />';
        $xml.='<RoomType id="'.$RoomType.'" closed="false" >';
        $xml.='<Inventory flexibleAllocation="1"/>';
        $xml.='<RatePlan id="'.$PoliticPrice.'" closed="false">';
        $xml.='<Rate currency="UAH">';
        $xml.='<PerDay rate="'.round($price).'" />';
        $xml.='</Rate>';
        $xml.='<Restrictions minLOS="'.$DateMinDay.'" maxLOS="28" closedToArrival="false" closedToDeparture="false" />';
        $xml.='</RatePlan>';
        $xml.='</RoomType>';
        $xml.='</AvailRateUpdate>';

        return $xml;
   }
   
   /* заливаем сезоны на экспедиа */
   function set_calendarAllExpediaSeazone($dv, $dv2, $ResourceOfferID, $prcent, $prcent1, $RoomType, $DateMinDay, $PoliticPrice)
   {
      $DS = &$this->_DS;
      $property = $this->_dataIntegration;
      $expediajson = $this->_expediajson;
      $HotelID = $this->set_hotelid($ResourceOfferID);
      
      $exchange =  $property->set_exchangerate();

      $calendar3 =  $property->set_seazone_now($dv, $ResourceOfferID);
      foreach($calendar3 as $row3)
      {
        $pricePrcent = $row3['OrderApartmentPersonDateF1Prise'] + (($row3['OrderApartmentPersonDateF1Prise'] / 100 ) * $prcent);
        if(!empty($prcent1))
        {
           $pricePrcent = $pricePrcent  + (($pricePrcent  / 100 ) * $prcent1);
        }      
        if( !empty($exchange[0]['ExchangeRateEUR']) )        
        {
           $pricePrcent = round($pricePrcent *  $exchange[0]['ExchangeRateEUR']); 
        } 
   
        if( strtotime($row3['OrderApartmentPersonDateStart']) < strtotime(date('Y-m-d')) )
        {
          $row3['OrderApartmentPersonDateStart'] = date('Y-m-d');
        }   
        if( strtotime($row3['OrderApartmentPersonDateStart']) != strtotime($row3['OrderApartmentPersonDateEnd']) )   
        {
           $xml=''; 
           $dateEnd = date("Y-m-d", strtotime("- 1 days", strtotime($row3['OrderApartmentPersonDateEnd'])));
           if( strtotime($dateEnd) == strtotime($row3['OrderApartmentPersonDateStart']) )
           {
               $dateEnd = $row3['OrderApartmentPersonDateEnd']; 
           }
           $DateMinDay = $row3['OrderApartmentPersonReservationDateMinDay'];
           $xml.='<AvailRateUpdate>';
           $xml.='<DateRange from="'.$row3['OrderApartmentPersonDateStart'].'" to="'.$dateEnd.'" />';
           $xml.='<RoomType id="'.$RoomType.'" closed="false" >';
           $xml.='<RatePlan id="'.$PoliticPrice.'" closed="false">';
           $xml.='<Rate currency="UAH">';
           $xml.='<PerDay rate="'.round($pricePrcent).'" />';
           $xml.='</Rate>';
           $xml.='<Restrictions minLOS="'.$DateMinDay.'" maxLOS="28" closedToArrival="false" closedToDeparture="false" />';
           $xml.='</RatePlan>';
           $xml.='</RoomType>';
           $xml.='</AvailRateUpdate>';
           $xmlSent = $expediajson->set_xml_available($xml, $HotelID);
           $expediajson->set_xml_available_send($ResourceOfferID, $xmlSent);
        }
      }
      
      return $xml;
   }
   
   /* заливаем сезоны на экспедиа персонально  */
   function set_calendarAllExpediaSeazonePerson($OrderApartmentPersonDateID, $prcent, $prcent1, $RoomType, $DateMinDay, $PoliticPrice)
   {
      $DS = &$this->_DS;
      $property = $this->_dataIntegration;
      $expediajson = $this->_expediajson;
      
      $exchange =  $property->set_exchangerate();

      $calendar3 =  $property->set_seazone_id($OrderApartmentPersonDateID);
      foreach($calendar3 as $row3)
      {
        
        $HotelID = $this->set_hotelid($row3['OrderApartmentPersonDateIDResourseOfferID']);
        $pricePrcent = $row3['OrderApartmentPersonDateF1Prise'] + (($row3['OrderApartmentPersonDateF1Prise'] / 100 ) * $prcent);
        if(!empty($prcent1))
        {
           $pricePrcent = $pricePrcent  + (($pricePrcent  / 100 ) * $prcent1);
        }      
        if( !empty($exchange[0]['ExchangeRateEUR']) )        
        {
           $pricePrcent = round($pricePrcent *  $exchange[0]['ExchangeRateEUR']); 
        } 
   
        if( strtotime($row3['OrderApartmentPersonDateStart']) < strtotime(date('Y-m-d')) )
        {
          $row3['OrderApartmentPersonDateStart'] = date('Y-m-d');
        }   
        if( strtotime($row3['OrderApartmentPersonDateStart']) != strtotime($row3['OrderApartmentPersonDateEnd']) )   
        {
           $xml=''; 
           $dateEnd = date("Y-m-d", strtotime("- 1 days", strtotime($row3['OrderApartmentPersonDateEnd'])));
           if( strtotime($dateEnd) == strtotime($row3['OrderApartmentPersonDateStart']) )
           {
               $dateEnd = $row3['OrderApartmentPersonDateEnd']; 
           }
           $DateMinDay = $row3['OrderApartmentPersonReservationDateMinDay'];
           $xml.='<AvailRateUpdate>';
           $xml.='<DateRange from="'.$row3['OrderApartmentPersonDateStart'].'" to="'.$dateEnd.'" />';
           $xml.='<RoomType id="'.$RoomType.'" closed="false" >';
           $xml.='<RatePlan id="'.$PoliticPrice.'" closed="false">';
           $xml.='<Rate currency="UAH">';
           $xml.='<PerDay rate="'.round($pricePrcent).'" />';
           $xml.='</Rate>';
           $xml.='<Restrictions minLOS="'.$DateMinDay.'" maxLOS="28" closedToArrival="false" closedToDeparture="false" />';
           $xml.='</RatePlan>';
           $xml.='</RoomType>';
           $xml.='</AvailRateUpdate>';
           $xmlSent = $expediajson->set_xml_available($xml, $HotelID);
           $expediajson->set_xml_available_send($ResourceOfferID, $xmlSent);
        }
      }
      return $xml;
   }
   
   
   /* закрываем даты на экспедиа */
   function set_calendarAllExpedia($dv, $dv2, $ResourceOfferID, $prcent, $prcent1, $RoomType, $DateMinDay)
   {
      $DS = &$this->_DS;
      $property = $this->_dataIntegration;
      
      $calendar1 =  $property->set_ica_all($dv, $dv2, $ResourceOfferID);
      $calendar2 =  $property->set_calendar_all($dv, $dv2, $ResourceOfferID);
      foreach($calendar1 as $row)
      {
            if( strtotime($row['dtstart']) < strtotime(date('Y-m-d'))  )
            {
               $row['dtstart'] = date('Y-m-d');
            }   
            if(strtotime($row['dtstart']) < strtotime($row['dtend']) )  
            {
               if( strtotime($row['dtstart']) < strtotime(date('Y-m-d')) ) 
               {
                  $row['dtstart'] = date('Y-m-d');
               }
               $dateEnd = date("Y-m-d", strtotime("- 1 days", strtotime( $row['dtend'])));
               $xml.='<AvailRateUpdate>';
               $xml.='<DateRange from="'.$row['dtstart'].'" to="'.$dateEnd.'"/>';
               $xml.='<RoomType id="'.$RoomType.'" closed="true">';
               $xml.='<Inventory totalInventoryAvailable="0"/>';
               $xml.='</RoomType>';
               $xml.='</AvailRateUpdate>';
            }
      }
      
      foreach($calendar2 as $row2)
      {
            if( strtotime($row2['OrderApartmentPersonDateStart']) < strtotime(date('Y-m-d'))  )
            {
               $row2['OrderApartmentPersonDateStart'] = date('d-m-Y');
            } 
            if(strtotime($row2['OrderApartmentPersonDateStart']) < strtotime($row2['OrderApartmentPersonDateEnd']) )  
            {
               if( strtotime($row2['OrderApartmentPersonDateStart']) < strtotime(date('Y-m-d')) ) 
               {
                  $row2['OrderApartmentPersonDateStart'] = date('Y-m-d');
               } 
               $to = date('Y-m-d', strtotime($row2['OrderApartmentPersonDateStart']));
               $dateEnd = date("Y-m-d", strtotime("- 1 days", strtotime($row2['OrderApartmentPersonDateEnd'])));
               $xml.='<AvailRateUpdate>';
               $xml.='<DateRange from="'.$to.'" to="'.$dateEnd.'"/>';
               $xml.='<RoomType id="'.$RoomType.'" closed="true">';
               $xml.='<Inventory totalInventoryAvailable="0"/>';
               $xml.='</RoomType>';
               $xml.='</AvailRateUpdate>';
            }
      }
      return $xml;
   }
   
   /*------------- закрываем даты на экспедиа персонально --------*/
   function set_calendarPersonExpedia($dv1, $dv2, $ResourceOfferID, $prcent, $prcent1, $RoomType, $DateMinDay)
   {
      $DS = &$this->_DS;
      $property = $this->_dataIntegration;
      $row['dtstart'] = $dv1;
      $row['dtend'] = $dv2;
      
      if( strtotime($row['dtstart']) < strtotime(date('Y-m-d'))  )
      {
         $row['dtstart'] = date('Y-m-d');
      }   
      if(strtotime($row['dtstart']) != strtotime($row['dtend']) )  
      {
          if( strtotime($row['dtstart']) < strtotime(date('Y-m-d')) ) 
          {
             $row['dtstart'] = date('Y-m-d');
          }
          $dateEnd = date("Y-m-d", strtotime("- 1 days", strtotime( $row['dtend'])));
          $dateEnd = $row['dtend'];
          $xml.='<AvailRateUpdate>';
          $xml.='<DateRange from="'.$row['dtstart'].'" to="'.$dateEnd.'"/>';
          $xml.='<RoomType id="'.$RoomType.'" closed="true">';
          $xml.='<Inventory totalInventoryAvailable="0"/>';
          $xml.='</RoomType>';
          $xml.='</AvailRateUpdate>';
      }
      return $xml;
   }
   
   
   function get_rumtype_id($rumtype_id)
   {
      global $CORE;
      $DS = &$this->_DS;
      $rumtype = $DS->query('SELECT * FROM sourcesdb_expedia_id WHERE expedia_type_room_id='.$rumtype_id);  
      return $rumtype;
   }
   
   function set_booking_expedia($id)
   {
      global $CORE;
      $DS = &$this->_DS;
      $list = $DS->query('SELECT * FROM confirmlist WHERE confirmListID='.$id); 
      return $list;
   }
   
   function update_confirmList($id)
   {
      global $CORE;
      $DS = &$this->_DS;
      $DS->query("UPDATE confirmList SET action='confirmed' WHERE confirmListID =".$id);
   }

   
   /* СОЗДАНИЕ НОВОГО ЗАКАЗА с Expedia и зпись закрытых дат в календарь  */
   function insertConfirmAsExpedia($bookingValue, $chanel)
   {
      global $CORE;
      $DS = &$this->_DS;
      $table = 'confirmList';
      $property = $this->_dataIntegration;
      if(!empty($bookingValue['roomtypeid']))
      {
          $rumtype = $this->get_rumtype_id($bookingValue['roomtypeid']);
          if( !empty($rumtype[0]['recourceoffers_id']) )
          {
              $logo = $DS->query('SELECT listSourcesLogo FROM listsources WHERE listSourcesDB="'.$chanel.'"');
              $valPrice = $DS->query("SELECT * FROM resourceoffer WHERE ResourceOfferID = ".$rumtype[0]['recourceoffers_id']); 
              $insertConfirm = date('Y-m-d H:s:i');
              $infAp = implode(",", $bookingValue['info_ap']);
              
              $input['confirmList'.DTR.'property_id'] = $rumtype[0]['recourceoffers_id']; 
              $input['confirmList'.DTR.'booking_code'] = $bookingValue['hotel_id'];
              $input['confirmList'.DTR.'check_in'] = $bookingValue['stdate'];
              $input['confirmList'.DTR.'check_out'] = $bookingValue['endate'];
              $input['confirmList'.DTR.'adults'] = $bookingValue['adult'];
              $input['confirmList'.DTR.'amount'] = $bookingValue['summ'];
              $input['confirmList'.DTR.'net_amount'] = $bookingValue['summ'];
              $input['confirmList'.DTR.'currency'] = $bookingValue['currency'];
              $input['confirmList'.DTR.'customer_name'] = $bookingValue['givenname'];
              $input['confirmList'.DTR.'customer_lastname'] = $bookingValue['surname'];      
              $input['confirmList'.DTR.'customer_email'] = $bookingValue['email'];
              $input['confirmList'.DTR.'customer_phone'] = $bookingValue['phone'];
              $input['confirmList'.DTR.'customer_country'] = $bookingValue['cart_country'];
              $input['confirmList'.DTR.'customer_language'] = $bookingValue['customer_language'];
              $input['confirmList'.DTR.'customer_comments'] = $bookingValue['customer_comments'];
              $input['confirmList'.DTR.'customer_comment'] = '';
              $input['confirmList'.DTR.'amount_my'] = $bookingValue['amount_my'];
              $input['confirmList'.DTR.'action'] = $bookingValue['action'];
              $input['confirmList'.DTR.'confirmListBookingID'] = 0;
              $input['confirmList'.DTR.'insertConfirm'] = $bookingValue['createdatetime'];
              $input['confirmList'.DTR.'chanel'] = $chanel;
              $input['confirmList'.DTR.'logo'] = $logo[0]['listSourcesLogo']; 
              $input['confirmList'.DTR.'customer_skype'] = '';
              $input['confirmList'.DTR.'customer_mobile'] = '';
              $input['confirmList'.DTR.'customer_fax'] = '';
              $input['confirmList'.DTR.'customer_postalcode'] = $bookingValue['cart_postalcode'];
              $input['confirmList'.DTR.'customer_pasport'] = '';
              $input['confirmList'.DTR.'customer_address'] = $bookingValue['cart_address'];
              $input['confirmList'.DTR.'createOrder'] = '';
              $input['confirmList'.DTR.'paid'] = '';
              $input['confirmList'.DTR.'confirmlistCountNew'] = 1;
              $input['confirmList'.DTR.'confirmlistDisplayNew'] = 1;
              $input['confirmList'.DTR.'confirmListBookingID'] =  $bookingValue['booking_id'];
              $input['confirmList'.DTR.'confirmlistInfoAp'] =  $infAp;
              $input['confirmList'.DTR.'confirmlistSource'] =  $bookingValue['source'];
              $input['confirmList'.DTR.'confirmlistType'] = $bookingValue['type'];
              
              
              $InsertID = $DS->insert($input, $table);

              if(!empty($InsertID ))
              {
                  $input1['confirmlistCartInfo'.DTR.'confirmlistCartInfoCardcode'] = $bookingValue['cardcode'];
                  $input1['confirmlistCartInfo'.DTR.'confirmlistCartInfoCardnumber'] = $bookingValue['cardnumber'];
                  $input1['confirmlistCartInfo'.DTR.'confirmlistCartInfoSeriescode'] = $bookingValue['seriescode'];
                  $input1['confirmlistCartInfo'.DTR.'confirmlistCartInfoExpiredate'] = $bookingValue['expiredate'];
                  $input1['confirmlistCartInfo'.DTR.'confirmlistCartInfoFIO'] = $bookingValue['givenname'];
                  $input1['confirmlistCartInfo'.DTR.'confirmlistCartInfoAddress'] = $bookingValue['cart_address'];
                  $input1['confirmlistCartInfo'.DTR.'confirmlistCartInfoCity'] = $bookingValue['cart_city'];
                  $input1['confirmlistCartInfo'.DTR.'confirmlistCartInfoPostalcode'] = $bookingValue['cart_postalcode'];
                  $input1['confirmlistCartInfo'.DTR.'confirmlistCartInfoconfirmlistID'] = $InsertID; 

                  $InsertID1 = $DS->insert($input1, 'confirmlistCartInfo');   
              }
              
                    
              $bookingValue['OrderApartmentPersonDateIDResourseOfferID'] = $rumtype[0]['recourceoffers_id'];  
              $bookingValue['OrderApartmentPersonDateStart']= $bookingValue['stdate']; 
              $bookingValue['OrderApartmentPersonDateEnd'] = $bookingValue['endate'];
              $bookingValue['OrderApartmentPersonDateTypeOrder']='2';
              $bookingValue['OrderApartmentPersonReservationDateMinDay'] = 3;
              $bookingValue['OrderApartmentPersonDateNow'] = $insertConfirm;
              
              $dateAvailability = $property->get_AvailabilityDate($bookingValue['stdate'], $bookingValue['endate'], $rumtype[0]['recourceoffers_id']);
              if( empty($dateAvailability))
              {
                 $idEnd=$property->createOrderapartmentpersondate($bookingValue);
                 $DS->query("UPDATE confirmList SET orderapartmentpersondateID='$idEnd' WHERE confirmListID =".$InsertID);
                 if( !empty($idEnd) )
                 {
                    $this->update_confirmList($InsertID);
                    
                 }
              }
              

              $params['SendMail'] ='Y';
              $CORE->sendMessage('admin','orderuser','newRequest.mail',date('d-m-Y H:i:s'),$bookingValue['booking_code'],'mail.getRequest',$params,'','NEW');
              
              return $InsertID; 
          }
      }
   }
   
     /* Обновление ЗАКАЗА с Expedia и зпись закрытых дат в календарь  */
   function insertConfirmAsExpediaUpdate($bookingValue, $chanel)
   {
      global $CORE;
      $DS = &$this->_DS;
      $table = 'confirmList';
      $property = $this->_dataIntegration;
      if(!empty($bookingValue['roomtypeid']) && !empty($bookingValue['confirmListID']) )
      {
          $rumtype = $this->get_rumtype_id($bookingValue['roomtypeid']);
          if( !empty($rumtype[0]['recourceoffers_id']) )
          {
              $logo = $DS->query('SELECT listSourcesLogo FROM listsources WHERE listSourcesDB="'.$chanel.'"');
              $valPrice = $DS->query("SELECT * FROM resourceoffer WHERE ResourceOfferID = ".$rumtype[0]['recourceoffers_id']); 
              $DS->query("DELETE orderapartmentpersondate WHERE OrderApartmentPersonDateID=".$bookingValue['OrderApartmentPersonDateID']);
              $insertConfirm = date('Y-m-d H:s:i');
              $infAp = implode(",", $bookingValue['info_ap']);
              
              $input['confirmList'.DTR.'property_id'] = $rumtype[0]['recourceoffers_id']; 
              $input['confirmList'.DTR.'booking_code'] = $bookingValue['hotel_id'];
              $input['confirmList'.DTR.'check_in'] = $bookingValue['stdate'];
              $input['confirmList'.DTR.'check_out'] = $bookingValue['endate'];
              $input['confirmList'.DTR.'adults'] = $bookingValue['adult'];
              $input['confirmList'.DTR.'amount'] = $bookingValue['summ'];
              $input['confirmList'.DTR.'net_amount'] = $bookingValue['summ'];
              $input['confirmList'.DTR.'currency'] = $bookingValue['currency'];
              $input['confirmList'.DTR.'customer_name'] = $bookingValue['givenname'];
              $input['confirmList'.DTR.'customer_lastname'] = $bookingValue['surname'];      
              $input['confirmList'.DTR.'customer_email'] = $bookingValue['email'];
              $input['confirmList'.DTR.'customer_phone'] = $bookingValue['phone'];
              $input['confirmList'.DTR.'customer_country'] = $bookingValue['cart_country'];
              $input['confirmList'.DTR.'customer_language'] = $bookingValue['customer_language'];
              $input['confirmList'.DTR.'customer_comments'] = $bookingValue['customer_comments'];
              $input['confirmList'.DTR.'amount_my'] = $bookingValue['amount_my'];
              $input['confirmList'.DTR.'action'] = $bookingValue['action'];
              $input['confirmList'.DTR.'confirmListBookingID'] = 0;
              $input['confirmList'.DTR.'insertConfirm'] = $bookingValue['createdatetime'];
              $input['confirmList'.DTR.'chanel'] = $chanel;
              $input['confirmList'.DTR.'logo'] = $logo[0]['listSourcesLogo']; 
              $input['confirmList'.DTR.'customer_postalcode'] = $bookingValue['cart_postalcode'];
              $input['confirmList'.DTR.'customer_address'] = $bookingValue['cart_address'];
              $input['confirmList'.DTR.'confirmlistCountNew'] = 1;
              $input['confirmList'.DTR.'confirmlistDisplayNew'] = 1;
              $input['confirmList'.DTR.'confirmListBookingID'] = $bookingValue['booking_id'];
              $input['confirmList'.DTR.'confirmlistInfoAp'] = $infAp;
              $input['confirmList'.DTR.'confirmlistSource'] = $bookingValue['source'];
              $input['confirmList'.DTR.'confirmlistType'] = $bookingValue['type'];
              
              $where='WHERE confirmListID ='.$bookingValue['confirmListID'];
              $DS->update($input, 'confirmList', $where);
              $InsertID = $bookingValue['confirmListID'];
              if( !empty($InsertID) )
              {
                  $input1['confirmlistCartInfo'.DTR.'confirmlistCartInfoCardcode'] = $bookingValue['cardcode'];
                  $input1['confirmlistCartInfo'.DTR.'confirmlistCartInfoCardnumber'] = $bookingValue['cardnumber'];
                  $input1['confirmlistCartInfo'.DTR.'confirmlistCartInfoSeriescode'] = $bookingValue['seriescode'];
                  $input1['confirmlistCartInfo'.DTR.'confirmlistCartInfoExpiredate'] = $bookingValue['expiredate'];
                  $input1['confirmlistCartInfo'.DTR.'confirmlistCartInfoFIO'] = $bookingValue['givenname'];
                  $input1['confirmlistCartInfo'.DTR.'confirmlistCartInfoAddress'] = $bookingValue['cart_address'];
                  $input1['confirmlistCartInfo'.DTR.'confirmlistCartInfoCity'] = $bookingValue['cart_city'];
                  $input1['confirmlistCartInfo'.DTR.'confirmlistCartInfoPostalcode'] = $bookingValue['cart_postalcode'];
 
                  $where1='WHERE confirmlistCartInfoconfirmlistID ='.$InsertID;
                  $DS->update($input1, 'confirmlistCartInfo', $where1);
                  
                  $InsertIDArr = $DS->query("SELECT confirmlistCartInfoID FROM confirmlistCartInfo WHERE confirmlistCartInfoconfirmlistID = ".$InsertID);
                  $InsertID1 = $InsertIDArr[0]['confirmlistCartInfoID'];
              }
                    
              $bookingValue['OrderApartmentPersonDateIDResourseOfferID'] = $rumtype[0]['recourceoffers_id'];  
              $bookingValue['OrderApartmentPersonDateStart']= $bookingValue['stdate']; 
              $bookingValue['OrderApartmentPersonDateEnd'] = $bookingValue['endate'];
              $bookingValue['OrderApartmentPersonDateTypeOrder']='2';
              $bookingValue['OrderApartmentPersonReservationDateMinDay'] = 3;
              $bookingValue['OrderApartmentPersonDateNow'] = $insertConfirm;
              
              $dateAvailability = $property->get_AvailabilityDate($bookingValue['stdate'], $bookingValue['endate'], $rumtype[0]['recourceoffers_id']);
              if( empty($dateAvailability) )
              {
                 $idEnd=$property->createOrderapartmentpersondate($bookingValue);
                 $DS->query("UPDATE confirmList SET orderapartmentpersondateID='$idEnd' WHERE confirmListID =".$InsertID);
                 if( !empty($idEnd) )
                 {
                    $this->update_confirmList($InsertID);
                 }
              }

              $params['SendMail'] ='Y';
              $CORE->sendMessage('admin','orderuser','newRequest.mail',date('d-m-Y H:i:s'),$bookingValue['booking_code'],'mail.getRequest',$params,'','NEW');
              
              return $InsertID;
          }
      }
   }
   

     /* Обновление ЗАКАЗА с Expedia и зпись закрытых дат в календарь  */
   function insertConfirmAsExpediaUpdateCancel($bookingValue, $chanel) 
   {
      global $CORE;
      $DS = &$this->_DS;
      $table = 'confirmList';
      $property = $this->_dataIntegration;
      if(!empty($bookingValue['confirmListID']) )
      {  
          $input['confirmList'.DTR.'action'] = $bookingValue['action'];
          $input['confirmList'.DTR.'insertConfirm'] = $bookingValue['createdatetime'];
          $input['confirmList'.DTR.'chanel'] = $chanel;
          $input['confirmList'.DTR.'confirmListBookingID'] = $bookingValue['booking_id'];
          $input['confirmList'.DTR.'confirmlistSource'] = $bookingValue['source'];
          $input['confirmList'.DTR.'confirmlistType'] = $bookingValue['type'];
          
          $where='WHERE confirmListID ='.$bookingValue['confirmListID'];
          $DS->update($input, 'confirmList', $where);
          $InsertID = $bookingValue['confirmListID'];
          $DS->query("DELETE orderapartmentpersondate WHERE OrderApartmentPersonDateID=".$bookingValue['OrderApartmentPersonDateID']);

          $params['SendMail'] ='Y';
          $CORE->sendMessage('admin','orderuser','newRequest.mail',date('d-m-Y H:i:s'),$bookingValue['booking_code'],'mail.getRequest',$params,'','NEW');
          return $InsertID;
      }
   }

    /* получаем заказа по confirmListBookingID */
   function listconfirmOrder($id)
   {
      global $CORE;
      $DS = &$this->_DS;
      $sql='SELECT * FROM confirmlist WHERE confirmListBookingID='.$id;
      $result = $DS->query($sql);
      return $result[0];
   }
   
   /* получаем заказа по id */
   function listconfirmListID($id)
   {
      global $CORE;
      $DS = &$this->_DS;
      $sql='SELECT * FROM confirmlist WHERE confirmListID='.$id;
      $result = $DS->query($sql);
      return $result[0];
   }
   

}
?>
   