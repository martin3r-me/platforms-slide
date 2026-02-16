<?php

namespace Platform\Slides\Enums;

enum FolderRole: string {
    case OWNER = 'owner';
    case ADMIN = 'admin';
    case MEMBER = 'member';
    case VIEWER = 'viewer';
}
