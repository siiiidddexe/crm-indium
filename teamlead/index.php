<?php
$pageTitle = 'Team Lead Dashboard - Obsiguard CRM';
require_once __DIR__ . '/../config/config.php';
requireAuth();

// Verify user is a team lead
if (!isTeamLead() && !isAdmin()) {
    header('Location: ' . APP_URL . '/login.php');
    exit;
}

$userId = $_SESSION['user_id'];

// Get team members
$teamMembers = getTeamMembers($userId);

// Get stats
$myContacts = db()->fetch("SELECT COUNT(*) as count FROM contacts WHERE assigned_to = ?", [$userId])['count'];
$myCalled = db()->fetch("SELECT COUNT(*) as count FROM contacts WHERE assigned_to = ? AND is_called = 1", [$userId])['count'];
$todayCalls = db()->fetch("SELECT COUNT(*) as count FROM call_logs WHERE user_id = ? AND DATE(call_time) = ?", [$userId, serverDate()])['count'];

// Team stats
$teamContacts = 0;
$teamCalled = 0;
foreach ($teamMembers as $member) {
    $teamContacts += db()->fetch("SELECT COUNT(*) as count FROM contacts WHERE assigned_to = ?", [$member['id']])['count'];
    $teamCalled += db()->fetch("SELECT COUNT(*) as count FROM contacts WHERE assigned_to = ? AND is_called = 1", [$member['id']])['count'];
}

// Handle contact assignment to team members
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $contactIds = $_POST['contact_ids'] ?? [];
    $assignTo = $_POST['assign_to'] ?? '';

    if (!empty($contactIds) && $assignTo) {
        $placeholders = implode(',', array_fill(0, count($contactIds), '?'));
        $params = array_merge([$assignTo], $contactIds);

        db()->update(
            "UPDATE contacts SET assigned_to = ? WHERE id IN ($placeholders)",
            $params
        );

        setFlash('success', count($contactIds) . ' contacts assigned successfully!');
        header('Location: index.php');
        exit;
    }
}

// Get unassigned contacts (assigned to this team lead)
$unassignedContacts = db()->fetchAll("
    SELECT c.*, cs.name as status_name, cs.color 
    FROM contacts c 
    LEFT JOIN call_statuses cs ON c.status_id = cs.id
    WHERE c.assigned_to = ? AND c.is_called = 0
    ORDER BY c.created_at DESC
", [$userId]);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="p-4 sm:p-6 lg:p-8">
    <!-- Page Header -->
    <div class="mb-8">
        <h1 class="text-2xl sm:text-3xl font-bold text-black">Team Dashboard</h1>
        <p class="text-gray-500 mt-1">Welcome back,
            <?= sanitize(currentUser()['name']) ?>
        </p>
    </div>

    <!-- Stats Grid -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <div class="bg-white rounded-2xl p-5 border border-gray-200 card-hover">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-xl bg-black flex items-center justify-center">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                </div>
            </div>
            <p class="text-2xl font-bold text-black">
                <?= count($teamMembers) ?>
            </p>
            <p class="text-sm text-gray-500">Team Members</p>
        </div>

        <div class="bg-white rounded-2xl p-5 border border-gray-200 card-hover">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-xl bg-blue-500 flex items-center justify-center">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                </div>
            </div>
            <p class="text-2xl font-bold text-black">
                <?= $myContacts ?>
            </p>
            <p class="text-sm text-gray-500">My Contacts</p>
        </div>

        <div class="bg-white rounded-2xl p-5 border border-gray-200 card-hover">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-xl bg-green-500 flex items-center justify-center">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
            </div>
            <p class="text-2xl font-bold text-black">
                <?= $teamCalled ?>
            </p>
            <p class="text-sm text-gray-500">Team Called</p>
        </div>

        <div class="bg-white rounded-2xl p-5 border border-gray-200 card-hover">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-xl bg-purple-500 flex items-center justify-center">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                    </svg>
                </div>
            </div>
            <p class="text-2xl font-bold text-black">
                <?= $todayCalls ?>
            </p>
            <p class="text-sm text-gray-500">Today's Calls</p>
        </div>
    </div>

    <div class="grid lg:grid-cols-2 gap-6">
        <!-- Team Members -->
        <div class="bg-white rounded-2xl border border-gray-200 p-6">
            <h2 class="text-lg font-bold text-black mb-4">Team Members</h2>
            <?php if (empty($teamMembers)): ?>
                <div class="text-center py-8 text-gray-500">
                    <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                    <p>No team members assigned</p>
                </div>
            <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($teamMembers as $member):
                        $memberContacts = db()->fetch("SELECT COUNT(*) as count FROM contacts WHERE assigned_to = ?", [$member['id']])['count'];
                        $memberCalled = db()->fetch("SELECT COUNT(*) as count FROM contacts WHERE assigned_to = ? AND is_called = 1", [$member['id']])['count'];
                        $progress = $memberContacts > 0 ? round(($memberCalled / $memberContacts) * 100) : 0;
                        ?>
                        <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-xl">
                            <div
                                class="w-10 h-10 rounded-full bg-black text-white flex items-center justify-center font-bold text-sm">
                                <?= strtoupper(substr($member['name'], 0, 1)) ?>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="font-medium truncate">
                                    <?= sanitize($member['name']) ?>
                                </p>
                                <div class="flex items-center gap-2 mt-1">
                                    <div class="flex-1 h-1.5 bg-gray-200 rounded-full overflow-hidden">
                                        <div class="h-full bg-black rounded-full" style="width: <?= $progress ?>%"></div>
                                    </div>
                                    <span class="text-xs text-gray-500">
                                        <?= $memberCalled ?>/
                                        <?= $memberContacts ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Assign Contacts to Team -->
        <div class="bg-white rounded-2xl border border-gray-200 p-6">
            <h2 class="text-lg font-bold text-black mb-4">Assign to Team</h2>
            <?php if (empty($unassignedContacts)): ?>
                <div class="text-center py-8 text-gray-500">
                    <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    <p>All contacts are assigned or called</p>
                </div>
            <?php elseif (empty($teamMembers)): ?>
                <div class="text-center py-8 text-gray-500">
                    <p>Add team members to assign contacts</p>
                </div>
            <?php else: ?>
                <form method="POST">
                    <div class="mb-4">
                        <select name="assign_to" required
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-black focus:border-black">
                            <option value="">-- Select Team Member --</option>
                            <?php foreach ($teamMembers as $member): ?>
                                <option value="<?= $member['id'] ?>">
                                    <?= sanitize($member['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="max-h-64 overflow-y-auto space-y-2 mb-4">
                        <?php foreach (array_slice($unassignedContacts, 0, 20) as $contact): ?>
                            <label class="flex items-center gap-3 p-3 bg-gray-50 rounded-xl cursor-pointer hover:bg-gray-100">
                                <input type="checkbox" name="contact_ids[]" value="<?= $contact['id'] ?>"
                                    class="w-5 h-5 rounded border-gray-300 text-black focus:ring-black">
                                <div class="flex-1">
                                    <p class="font-medium text-sm">
                                        <?= sanitize($contact['name']) ?>
                                    </p>
                                    <p class="text-xs text-gray-500">
                                        <?= sanitize($contact['phone']) ?>
                                    </p>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <button type="submit"
                        class="w-full px-4 py-3 bg-black text-white rounded-xl font-medium hover:bg-gray-800 transition-colors">
                        Assign Selected
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>