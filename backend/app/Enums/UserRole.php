<?php

namespace App\Enums;

enum UserRole: string { case SUPER_ADMIN = 'SUPER_ADMIN'; case TENANT_ADMIN = 'TENANT_ADMIN'; case AGENT = 'AGENT'; }
