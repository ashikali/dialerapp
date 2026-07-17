<?php

return ['freeswitch'=>['xml_token'=>env('FREESWITCH_XML_TOKEN'),'esl_host'=>env('FREESWITCH_ESL_HOST','127.0.0.1'),'esl_port'=>(int)env('FREESWITCH_ESL_PORT',8021),'esl_password'=>env('FREESWITCH_ESL_PASSWORD')]];
