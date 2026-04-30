<?php
$pageTitle = 'My Team - Obsiguard CRM';
require_once __DIR__ . '/../config/config.php';
requireAuth();

if (!isTeamLead() && !isAdmin()) {
    header('Location: ' . APP_URL . '/login.php');
    exit;
}

$userId = $_SESSION['user_id'];

// Get team members with stats
$teamMembers = db()->fetchAll("
    SELECT u.*,
        (SELECT COUNT(*) FROM contacts WHERE assigned_to = u.id) as total_contacts,
        (SELECT COUNT(*) FROM contacts WHERE assigned_to = u.id AND is_called = 1) as called_contacts,
        (SELECT COUNT(*) FROM call_logs WHERE user_id = u.id AND DATE(call_time) = ?) as today_calls
    FROM users u 
    WHERE u.teamlead_id = ? AND u.role = 'employee' AND u.is_active = 1
    ORDER BY u.name
", [serverDate(), $userId]);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="p-4 sm:p-6 lg:p-8">
    <!-- Page Header -->
    <div class="mb-6">
        <h1 class="text-2xl sm:text-3xl font-bold text-black">My Team</h1>
        <p class="text-gray-500 mt-1">
            <?= count($teamMembers) ?> team members
        </p>
    </div>

    <?php if (empty($teamMembers)): ?>
        <div class="bg-white rounded-2xl border border-gray-200 p-12 text-center text-gray-500">
            <svg class="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
            </svg>
            <p class="text-lg font-medium">No team members yet</p>
            <p class="mt-1">Contact admin to assign employees to your team</p>
        </div>
    <?php else: ?>
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php foreach ($teamMembers as $member):
                $progress = $member['total_contacts'] > 0 ? round(($member['called_contacts'] / $member['total_contacts']) * 100) : 0;
                ?>
                <div class="bg-white rounded-2xl border border-gray-200 p-6 card-hover">
                    <div class="flex items-center gap-4 mb-4">
                        <div
                            class="w-14 h-14 rounded-full bg-black text-white flex items-center justify-center font-bold text-lg">
                            <?= strtoupper(substr($member['name'], 0, 1)) ?>
                        </div>
                        <div>
                            <h3 class="font-bold text-lg">
                                <?= sanitize($member['name']) ?>
                            </h3>
                            <p class="text-sm text-gray-500">
                                <?= sanitize($member['email']) ?>
                            </p>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-4 mb-4">
                        <div class="text-center">
                            <p class="text-xl font-bold">
                                <?= $member['total_contacts'] ?>
                            </p>
                            <p class="text-xs text-gray-500">Assigned</p>
                        </div>
                        <div class="text-center">
                            <p class="text-xl font-bold text-green-600">
                                <?= $member['called_contacts'] ?>
                            </p>
                            <p class="text-xs text-gray-500">Called</p>
                        </div>
                        <div class="text-center">
                            <p class="text-xl font-bold text-blue-600">
                                <?= $member['today_calls'] ?>
                            </p>
                            <p class="text-xs text-gray-500">Today</p>
                        </div>
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-sm text-gray-600">Progress</span>
                            <span class="text-sm font-medium">
                                <?= $progress ?>%
                            </span>
                        </div>
                        <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-full bg-black rounded-full" style="width: <?= $progress ?>%"></div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>