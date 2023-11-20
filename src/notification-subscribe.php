<?php
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);

    header('Content-Type: application/json; charset=utf-8');

    $subscriptionData = json_decode(file_get_contents('php://input'), true);

    if(file_exists('endpoints.json')){
        $json = json_decode(file_get_contents('endpoints.json'));

        array_push($json, $subscriptionData);
        file_put_contents('endpoints.json', json_encode($json, JSON_PRETTY_PRINT));

    }else{
        $json = [
            $subscriptionData
        ];
        file_put_contents('endpoints.json', json_encode($json, JSON_PRETTY_PRINT));
    }
    
    $response = [
        'data' => [
            'success' => true
        ]
    ];

    print_r(json_encode($response));
?>
