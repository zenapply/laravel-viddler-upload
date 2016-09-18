<?php

return [

    "auth" => [
        "key" => env("VIDDLER_KEY"),
        "user" => env("VIDDLER_USER"),
        "pass" => env("VIDDLER_PASS"),
    ],

    "table" => "videos",

    "storage" => [
        "disk" => [
            'new'        => "default",
            'converting' => "default",
            'uploading'  => "default",
            'encoding'   => "default",
            'finished'   => "default",
            'error'      => "default",
        ],
    ],

    "convert" => [
        "enabled" => true,

        "mimes" => [
            "video/quicktime" => "video/mp4",
            "application/octet-stream" => "video/mp4"
        ]
    ],

    "mimes" => [
        "video/x-msvideo",
        "video/mp4",
        "video/x-m4v",
        "video/x-flv",
        "video/quicktime",
        "video/x-ms-wmv",
        "video/mpeg",
        "video/3gpp",
        "video/x-ms-asf",
        "application/octet-stream",
    ],
    
    "queue" => [
        "enabled" => true,
    ],
];