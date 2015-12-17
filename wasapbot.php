<?php

  // --------------------------------------------------------------------------------------------------------------- //
  //  wasapbot by @gojigeje - A super simple WhatsApp bot                                                            //
  // --------------------------------------------------------------------------------------------------------------- //
  //    Author      : Ghozy Arif Fajri < gojigeje @ gmail.com >                                                      //
  //    Github      : https://github.com/gojigeje                                                                    //
  //    Social      : https://twitter.com/gojigeje                                                                   //
  //    Chat        : https://telegram.me/gojigeje                                                                   //
  // --------------------------------------------------------------------------------------------------------------- //
  //                                                                                                                 //
  //    WARNING!  Code is very unclean, not professional, not modular, not OOP, product of newbie. Probably buggy    //
  //              as hell too. You won't find anything special here, just putting things to work together.           //
  //                                                                                                                 //
  // --------------------------------------------------------------------------------------------------------------- //
  //  uses Chat-API - https://github.com/WHAnonymous/Chat-API                                                        //
  // --------------------------------------------------------------------------------------------------------------- //
  
  error_reporting(E_ERROR | E_WARNING | E_PARSE);
  date_default_timezone_set('Asia/Jakarta');
  $debug = false;

  require_once 'whatsapp/src/whatsprot.class.php';

  // bot configuration 
  // dapetin passwordnya dg script registerTool.php
  // --> whatsapp/examples/registerTool.php

  $nickname = "wasapbot";                          // bot nickname
  $username = "62xxxxxxxxxxx";                     // bot number
  $password = "C34G7Cauuw8lbxxxxxxxxxxxxxxxx";     // password


  // --------------------------------------------------------------------------------------------------------------- //
  // --------------------------------------------------------------------------------------------------------------- //
  //   Edit script di bawah kalau udah faham tentang Chat-API, backup dulu sebelum edit!                             //
  //   Baca-baca dulu wikinya --> https://github.com/WHAnonymous/Chat-API/wiki                                       //
  // --------------------------------------------------------------------------------------------------------------- //
  // --------------------------------------------------------------------------------------------------------------- //
  
  // restart script pas error -----------------------------------------------------------------------------------------
  $ke = ++$argv[1]; $_ = $_SERVER['_']; $poll = 0;
  $restartMyself = function () {
      global $_, $argv, $ke;
      echo "\n[$ke][".date('H:i:s')."] - Ada yang salah.. Me-restart bot..\n";
      sleep(10);
      pcntl_exec($_, $argv);
  };
  set_exception_handler($restartMyself); 
  register_shutdown_function($restartMyself);
  pcntl_signal(SIGTERM, $restartMyself); // kill
  pcntl_signal(SIGHUP,  $restartMyself); // kill -s HUP or kill -1
  // pcntl_signal(SIGINT,  $restartMyself); // Ctrl-C

  echo "[$ke][".date('H:i:s')."] ----------------------------------------------------\n";
  echo "[$ke][".date('H:i:s')."] - Login sebagai '$nickname' ($username)\n";
  cek_konek();
  $w = new WhatsProt($username, $nickname, $debug);
  
  // bind events ------------------------------------------------------------------------------------------------------
  // list semua event -->  https://github.com/WHAnonymous/Chat-API/wiki/WhatsAPI-Documentation#list-of-all-events
  $w->eventManager()->bind("onConnect", "onConnect");
  $w->eventManager()->bind("onDisconnect", "onDisconnect");
  $w->eventManager()->bind("onClose", "onClose");
  $w->eventManager()->bind("onGetMessage", "onGetMessage");
  $w->eventManager()->bind("onGetGroupMessage", "onGetGroupMessage");

  // konek ------------------------------------------------------------------------------------------------------------
  sleep(3);
  $w->connect();
  $w->loginWithPassword($password);
  $w->sendGetServerProperties();
  $w->sendClientConfig();
  $w->sendGetGroups();
  $w->sendPing();

  // poll message loop ------------------------------------------------------------------------------------------------
  while (1) {

    if ($poll==5) {
      echo "\n[$ke][".date('H:i:s')."] ----------------------------------------------------\n";
      echo "[$ke][".date('H:i:s')."] --- BOT SIAP!\n";
      echo "[$ke][".date('H:i:s')."] --- \n";
      echo "[$ke][".date('H:i:s')."] --- Sekarang coba kirim pesan ke bot ini,\n";
      echo "[$ke][".date('H:i:s')."] --- seharusnya bot akan mengirim balik teks yang\n";
      echo "[$ke][".date('H:i:s')."] --- dikirimkan ke dia.\n";
      echo "[$ke][".date('H:i:s')."] --- \n";
      echo "[$ke][".date('H:i:s')."] --- Ubah perilaku bot dengan mengedit function\n";
      echo "[$ke][".date('H:i:s')."] --- onGetMessage() dan onGetGroupMessage()\n";
      echo "[$ke][".date('H:i:s')."] --- di baris 126 dan baris 182.\n";
      echo "[$ke][".date('H:i:s')."] --- \n";
      echo "[$ke][".date('H:i:s')."] --- Semoga sukses!\n";
      echo "[$ke][".date('H:i:s')."] ----------------------------------------------------\n";
    }

    $w->pollMessage(true); // markAsRead

    // cek konek
    if ($poll % 200 == 0 && $poll != 0) {
      cek_konek();
    }

    $poll++; // poll control
  }


  // ------------------------------------------------------------------------------------------------------------------
  // FUNCTIONS 
  // ------------------------------------------------------------------------------------------------------------------

  // ketika dapat pesan pm
  function onGetMessage($mynumber, $from, $id, $type, $time, $name, $body)
  {
    global $ke, $w, $poll;
    $bodi = str_replace( array("\n", "\r\n", "\r") , " ", $body);
    $from = str_replace(array("@s.whatsapp.net","@g.us"), "", $from);  // nomer user
    $user = explode(' ',trim($name)); $nama = $user[0];                // ambil nama depan

    echo "\n[$ke][".date('H:i:s')."] [$from]\n";
    echo "--- $name > $bodi\n";

    // coba tampilkan semua parameter yang didapat ketika ada pm masuk
    echo "mynumber  : $mynumber\n";
    echo "from      : $from\n";
    echo "id        : $id\n";
    echo "type      : $type\n";
    echo "time      : $time\n";
    echo "name      : $name\n";
    echo "body      : $body\n";


    if ($poll > 5) { // abaikan poll-poll awal, biar nggak ngeflood

      // dari sini udah bisa bikin logic botnya..
      // misalnya nge-echo balik apa chat yg dikirim ke botnya
      // --------------------------------------------------------------------------------------------

            // kirim typing..
            $w->sendMessageComposing($from);
            // selalu kasih jeda barang 2-3 detik, biar ga dikira bot
            sleep(3);
            // kirim balik
            $w->sendMessage($from, $body); // kirim ke orangnya
            // setelah send apapun, selalu poll
            $w->pollMessage();


      // atau nge-respon perintah tertentu
      // --------------------------------------------------------------------------------------------

      //      // sesuaikan responnya dulu
      //      if ($body == "!ping") {
      //        $respon = "pong! $nama";
      //      }
      //      elseif ($body == "!help") {
      //        $respon = "ada yang bisa dibantu, bos $nama? 😎";
      //      }
      //    
      //      // jika ada respon, kirimkan responnya
      //      if (!empty($respon)) {
      //        // dikirim  belakangan
      //        $w->sendMessageComposing($from); // kirim ke orangnya
      //        sleep(3);
      //        $w->sendMessage($from, $respon);
      //        $w->pollMessage();
      //      }

    }

  }

  // pesan di grup
  function onGetGroupMessage($mynumber, $from_group_jid, $from_user_jid, $id, $type, $time, $name, $body)
  {
    global $ke, $w, $poll;
    $from_group = str_replace(array("@s.whatsapp.net","@g.us"), "", $from_group_jid);     // id grup
    $from_user = str_replace(array("@s.whatsapp.net","@g.us"), "", $from_user_jid);       // nomer user
    $bodi = str_replace( array("\n", "\r\n", "\r") , " ", $body);
    $user = explode(' ',trim($name)); $nama = $user[0];                                   // ambil nama depan

    echo "\n[$ke][".date('H:i:s')."] [$from_group_jid]\n";
    echo "--- grup: $from_group | user : $from_user\n";
    echo "--- $name > $bodi\n";

    // coba tampilkan semua parameter yg didapat ketika ada pesan baru di grup
    echo "mynumber        : $mynumber\n";
    echo "from_group_jid  : $from_group_jid\n";
    echo "from_group      : $from_group\n";
    echo "from_user_jid   : $from_user_jid\n";
    echo "from_user       : $from_user\n";
    echo "id              : $id\n";
    echo "type            : $type\n";
    echo "time            : $time\n";
    echo "name            : $name\n";
    echo "body            : $body\n";

    if ($poll > 5) { // abaikan poll-poll awal, biar nggak ngeflood di grup

      // dari sini udah bisa bikin logic botnya..
      // misalnya nge-echo balik apa chat yg dikirim grup sama orang lain
      // --------------------------------------------------------------------------------------------

      //     // kirim typing..
      //     $w->sendMessageComposing($from_group); // kirim ke groupnya
      //     // selalu kasih jeda barang 2-3 detik, biar ga dikira bot
      //     sleep(3);
      //     // kirim balik
      //     $w->sendMessage($from_group, $body);
      //     // setelah send apapun, selalu poll
      //     $w->pollMessage();


      // atau nge-respon perintah tertentu
      // --------------------------------------------------------------------------------------------

           // sesuaikan responnya dulu
           if ($body == "!ping") {
             $respon = "pong! $nama";
           }
           elseif ($body == "!help") {
             $respon = "ada yang bisa dibantu, bos $nama? 😎";
           }

           // jika ada respon, kirimkan responnya
           if (!empty($respon)) {
             // dikirim  belakangan
             $w->sendMessageComposing($from_group); // kirim ke grup
             sleep(3);
             $w->sendMessage($from_group, $respon);
             $w->pollMessage();
           }

    }

  }

  function onConnect($mynumber, $socket)
  {
    global $ke;
    echo "[$ke][".date('H:i:s')."] - $mynumber sukses login!!\n";
    echo "[$ke][".date('H:i:s')."] - Tunggu hingga bot siap!\n";
    echo "[$ke][".date('H:i:s')."] ----------------------------------------------------\n";
  }

  function onDisconnect($mynumber, $socket)
  {
    global $ke;
    echo "\n[$ke][".date('H:i:s')."] - $mynumber koneksi terputus!\n";
    exit(1);
  }

  function onClose( $mynumber, $error )
  {
    global $ke;
    echo "\n[$ke][".date('H:i:s')."] EVENT: onClose\n";
    echo " mynumber : $mynumber\n";
    echo " error    : $error\n\n";
  }

  function cek_konek() { 
    global $ke;
    $connected = @fsockopen("www.google.com", 80); 
    if ($connected){
      fclose($connected);
    } else {
      echo "\n[$ke][".date('H:i:s')."] Tidak bisa mengakses internet! coba lagi dalam 15 detik..\n";
      sleep(15);
      $connected2 = @fsockopen("www.google.com", 80); 
      if ($connected2) {
        fclose($connected2);
      } else {
        echo "\n[$ke][".date('H:i:s')."] EROR: Tidak bisa mengakses internet!\n";
        exit(1);
      }
    }
  }

 ?>
