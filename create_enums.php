<?php

$files = [];

// Enums
$files['app/Enums/ProjectStatus.php'] = <<<'PHP'
<?php

namespace App\Enums;

enum ProjectStatus: string
{
    case DRAFT = 'draft';
    case ACTIVE = 'active';
    case ON_HOLD = 'on_hold';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
}
PHP;

$files['app/Enums/TaskStatus.php'] = <<<'PHP'
<?php

namespace App\Enums;

enum TaskStatus: string
{
    case PENDING = 'pending';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
}
PHP;

$files['app/Enums/TaskPriority.php'] = <<<'PHP'
<?php

namespace App\Enums;

enum TaskPriority: string
{
    case LOW = 'low';
    case MEDIUM = 'medium';
    case HIGH = 'high';
    case URGENT = 'urgent';
}
PHP;

// Write files
foreach ($files as $path => $content) {
    $dir = dirname(__DIR__.'/'.$path);
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    file_put_contents(__DIR__.'/'.$path, $content);
    echo "Created: $path\n";
}
