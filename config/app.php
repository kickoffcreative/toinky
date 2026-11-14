<?php
// Public site address; never derive email/payment links from a request header.
return [
    'base_url'=>'https://www.toinky.com',
    'allowed_hosts'=>['www.toinky.com','toinky.com'],
];
