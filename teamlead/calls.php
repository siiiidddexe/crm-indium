<?php
$pageTitle = 'Calling Cards - Obsiguard CRM';
require_once __DIR__ . '/../config/config.php';
requireTeamLead();

$userId       = $_SESSION['user_id'];
$notesEnabled = getSetting('enable_notes', '0') === '1';

// Get call statuses
$statuses = getCallStatuses();

// Get WhatsApp templates
$whatsappTemplates = getWhatsAppTemplates();

// Get languages for move request (only needed when notes disabled)
$languages = $notesEnabled ? [] : getLanguages();

// Get pending contacts - LAZY LOADING (only first 20)
$contacts = db()->fetchAll("
    SELECT c.*
    FROM contacts c
    WHERE c.assigned_to = ? AND c.is_called = 0
    ORDER BY c.id DESC
    LIMIT 20
", [$userId]);

// Get total count for progress bar
$totalPending = db()->fetch("SELECT COUNT(*) as count FROM contacts WHERE assigned_to = ? AND is_called = 0", [$userId])['count'];

require_once __DIR__ . '/../includes/header.php';
?>

<div class="p-4 sm:p-6 lg:p-8" id="callsContainer">
    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-black">Calling Cards</h1>
            <p class="text-gray-500 mt-1"><span id="pendingCount"><?= $totalPending ?></span> contacts pending</p>
        </div>

        <?php if (!empty($contacts)): ?>
            <!-- Auto Mode Toggle + Hot Lead -->
            <div class="flex items-center gap-3">
                <button onclick="showModal('hotLeadModal')" id="hotLeadBtn"
                    class="inline-flex items-center gap-2 bg-red-50 border border-red-200 text-red-700 rounded-xl px-4 py-2.5 font-medium text-sm hover:bg-red-100 active:scale-[0.98] transition-all">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 23c-1.09 0-2.16-.18-3.16-.52C5.28 21.12 2 17.27 2 13.08c0-2.13.86-4.07 2.25-5.48C5.64 6.22 7.5 4.5 9 2c.56 1.42 1.56 2.85 3 4.28C13.44 4.85 14.44 3.42 15 2c1.5 2.5 3.36 4.22 4.75 5.6A7.722 7.722 0 0 1 22 13.08c0 4.19-3.28 8.04-6.84 9.4-1 .34-2.07.52-3.16.52z"/>
                    </svg>
                    Hot Lead
                </button>
                <label
                    class="inline-flex items-center gap-3 cursor-pointer bg-white border border-gray-200 rounded-xl px-4 py-2.5">
                    <span class="text-sm font-medium text-gray-700">Auto Mode</span>
                    <div class="relative">
                        <input type="checkbox" id="autoModeToggle" class="sr-only peer">
                        <div
                            class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-500">
                        </div>
                    </div>
                </label>
            </div>
        <?php endif; ?>
    </div>

    <?php if (empty($contacts)): ?>
        <!-- Empty State -->
        <div class="bg-white rounded-2xl border border-gray-200 p-12 text-center max-w-md mx-auto">
            <div class="w-24 h-24 rounded-full bg-green-100 flex items-center justify-center mx-auto mb-6">
                <svg class="w-12 h-12 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
            </div>
            <h2 class="text-2xl font-bold text-black mb-2">All Done!</h2>
            <p class="text-gray-500 mb-6">You've called all your assigned contacts. Great work!</p>
            <a href="index.php"
                class="inline-flex items-center gap-2 px-6 py-3 bg-black text-white rounded-xl font-medium hover:bg-gray-800 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                Back to Dashboard
            </a>
        </div>
    <?php else: ?>

        <!-- Progress Indicator -->
        <div class="bg-white rounded-xl border border-gray-200 p-4 mb-6">
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm font-medium text-gray-600">Progress</span>
                <span class="text-sm font-bold" id="progressText">0 / <?= $totalPending ?></span>
            </div>
            <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                <div id="progressBar" class="h-full bg-green-500 rounded-full transition-all duration-500"
                    style="width: 0%"></div>
            </div>
        </div>

        <!-- Calling Cards Stack -->
        <div id="callingCards" class="space-y-4">
            <?php foreach ($contacts as $index => $contact): ?>
                <div class="calling-card bg-white rounded-2xl border-2 border-gray-200 overflow-hidden transition-all duration-300 <?= $index === 0 ? 'border-black shadow-lg' : 'opacity-60' ?>"
                    data-id="<?= $contact['id'] ?>" data-phone="<?= sanitize($contact['phone']) ?>"
                    data-name="<?= sanitize($contact['name']) ?>" data-index="<?= $index ?>">

                    <div class="p-5 sm:p-6">
                        <div class="flex items-start gap-4">
                            <!-- Avatar -->
                            <div
                                class="w-14 h-14 sm:w-16 sm:h-16 rounded-full bg-gradient-to-br from-gray-800 to-black text-white flex items-center justify-center font-bold text-xl sm:text-2xl flex-shrink-0 shadow-md">
                                <?= strtoupper(substr($contact['name'], 0, 1)) ?>
                            </div>

                            <!-- Contact Info -->
                            <div class="flex-1 min-w-0">
                                <h2 class="text-xl sm:text-2xl font-bold text-black truncate"><?= sanitize($contact['name']) ?>
                                </h2>
                                <p class="text-lg sm:text-xl text-gray-600 font-mono mt-1"><?= sanitize($contact['phone']) ?>
                                </p>
                                <?php if ($contact['notes']): ?>
                                    <p class="text-sm text-gray-400 mt-2 line-clamp-2"><?= sanitize($contact['notes']) ?></p>
                                <?php endif; ?>
                            </div>

                            <!-- Card Number -->
                            <div class="bg-gray-100 px-3 py-1 rounded-full">
                                <span class="text-sm font-bold text-gray-600"><?= $index + 1 ?>/<?= $totalPending ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="card-actions border-t border-gray-100 p-4 bg-gray-50">
                        <div class="state-call flex gap-2">
                            <button onclick="initiateCall(<?= $contact['id'] ?>, '<?= sanitize($contact['phone']) ?>')"
                                class="flex-1 py-4 bg-green-500 text-white rounded-xl font-bold text-base flex items-center justify-center gap-2 hover:bg-green-600 active:scale-[0.98] transition-all pulse-call">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                </svg>
                                CALL
                            </button>
                            <button onclick="openTemplatePicker('<?= sanitize($contact['phone']) ?>', '<?= sanitize($contact['name']) ?>')"
                                class="flex-1 py-4 bg-emerald-600 text-white rounded-xl font-bold text-base flex items-center justify-center gap-2 hover:bg-emerald-700 active:scale-[0.98] transition-all">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z" /></svg>
                                WHATSAPP
                            </button>
                            <?php if ($notesEnabled): ?>
                            <button onclick="openNotesModal(<?= $contact['id'] ?>)"
                                class="py-4 px-3 bg-purple-500 text-white rounded-xl font-bold text-base flex items-center justify-center gap-1 hover:bg-purple-600 active:scale-[0.98] transition-all" title="Notes">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                NOTES
                            </button>
                            <?php else: ?>
                            <button onclick="openMoveModal(<?= $contact['id'] ?>)"
                                class="py-4 px-3 bg-orange-500 text-white rounded-xl font-bold text-base flex items-center justify-center gap-1 hover:bg-orange-600 active:scale-[0.98] transition-all" title="Move">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129" /></svg>
                                MOVE
                            </button>
                            <?php endif; ?>
                        </div>
                        <div class="state-endcall hidden">
                            <div class="flex gap-2">
                                <button onclick="endCall(<?= $contact['id'] ?>)"
                                    class="flex-1 py-4 bg-red-500 text-white rounded-xl font-bold text-lg flex items-center justify-center gap-3 hover:bg-red-600 active:scale-[0.98] transition-all">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 8l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2M5 3a2 2 0 00-2 2v1c0 8.284 6.716 15 15 15h1a2 2 0 002-2v-3.28a1 1 0 00-.684-.948l-4.493-1.498a1 1 0 00-1.21.502l-1.13 2.257a11.042 11.042 0 01-5.516-5.517l2.257-1.128a1 1 0 00.502-1.21L9.228 3.683A1 1 0 008.28 3H5z" /></svg>
                                    CALL ENDED - SELECT STATUS
                                </button>
                                <button onclick="openTemplatePicker('<?= sanitize($contact['phone']) ?>', '<?= sanitize($contact['name']) ?>')"
                                    class="py-4 px-3 bg-emerald-600 text-white rounded-xl font-bold text-base flex items-center justify-center gap-1 hover:bg-emerald-700 active:scale-[0.98] transition-all" title="WhatsApp">
                                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                                    WA
                                </button>
                                <?php if ($notesEnabled): ?>
                                <button onclick="openNotesModal(<?= $contact['id'] ?>)"
                                    class="py-4 px-3 bg-purple-500 text-white rounded-xl font-bold text-base flex items-center justify-center gap-1 hover:bg-purple-600 active:scale-[0.98] transition-all" title="Notes">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                    NOTES
                                </button>
                                <?php else: ?>
                                <button onclick="openMoveModal(<?= $contact['id'] ?>)"
                                    class="py-4 px-3 bg-orange-500 text-white rounded-xl font-bold text-base flex items-center justify-center gap-1 hover:bg-orange-600 active:scale-[0.98] transition-all" title="Move">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129" /></svg>
                                    MOVE
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Status Selection Modal -->
<div id="statusModal"
    class="fixed inset-0 bg-black bg-opacity-60 z-50 hidden flex items-end sm:items-center justify-center"
    data-modal-backdrop>
    <div
        class="bg-white rounded-t-3xl sm:rounded-2xl w-full sm:max-w-md max-h-[85vh] overflow-hidden flex flex-col animate-slide-up">
        <div class="p-5 border-b border-gray-200 bg-white sticky top-0">
            <h2 class="text-xl font-bold text-black">Call Outcome</h2>
            <p class="text-sm text-gray-500 mt-1" id="statusModalContact"></p>
        </div>
        <div class="p-4 space-y-2 overflow-y-auto flex-1">
            <!-- Comment field -->
            <div class="mb-3">
                <label class="block text-sm font-medium text-gray-600 mb-1">Add comment (optional)</label>
                <textarea id="statusComment" rows="2"
                    class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-black focus:border-black resize-none"
                    placeholder="Type a comment about this call..."></textarea>
            </div>
            <?php foreach ($statuses as $status): ?>
                <button onclick="saveStatus(<?= $status['id'] ?>)"
                    class="w-full p-4 rounded-xl border-2 border-gray-200 hover:border-gray-900 active:scale-[0.98] transition-all flex items-center gap-4 text-left group">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center transition-transform group-hover:scale-110"
                        style="background-color: <?= $status['color'] ?>20">
                        <div class="w-5 h-5 rounded-full" style="background-color: <?= $status['color'] ?>"></div>
                    </div>
                    <span class="font-semibold text-lg"><?= sanitize($status['name']) ?></span>
                    <svg class="w-5 h-5 text-gray-400 ml-auto opacity-0 group-hover:opacity-100 transition-opacity"
                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </button>
            <?php endforeach; ?>
        </div>
        <div class="p-4 border-t border-gray-100 bg-gray-50">
            <button onclick="hideModal('statusModal')"
                class="w-full py-3 bg-gray-200 rounded-xl font-medium hover:bg-gray-300 transition-colors">
                Cancel
            </button>
        </div>
    </div>
</div>

<!-- Timer Overlay -->
<div id="timerOverlay"
    class="fixed inset-0 bg-gradient-to-br from-gray-900 to-black z-50 hidden flex flex-col items-center justify-center">
    <div class="text-center text-white">
        <div class="relative w-40 h-40 mx-auto mb-8">
            <svg class="w-40 h-40 transform -rotate-90">
                <circle cx="80" cy="80" r="72" stroke="rgba(255,255,255,0.1)" stroke-width="10" fill="none" />
                <circle id="timerCircle" cx="80" cy="80" r="72" stroke="white" stroke-width="10" fill="none"
                    stroke-dasharray="452" stroke-dashoffset="0" stroke-linecap="round" />
            </svg>
            <span id="timerNumber" class="absolute inset-0 flex items-center justify-center text-6xl font-bold">3</span>
        </div>
        <p class="text-2xl font-medium opacity-90">Next contact in...</p>
        <button onclick="cancelAutoNext()"
            class="mt-6 px-6 py-3 bg-white bg-opacity-20 rounded-xl text-white font-medium hover:bg-opacity-30 transition-colors">
            Cancel Auto-Next
        </button>
    </div>
</div>

<?php if ($notesEnabled): ?>
<!-- Notes Modal -->
<div id="notesModal"
    class="fixed inset-0 bg-black bg-opacity-60 z-50 hidden flex items-end sm:items-center justify-center"
    data-modal-backdrop>
    <div class="bg-white rounded-t-3xl sm:rounded-2xl w-full sm:max-w-md max-h-[85vh] overflow-hidden flex flex-col animate-slide-up">
        <div class="p-5 border-b border-gray-200 flex items-center justify-between">
            <div>
                <h2 class="text-xl font-bold text-black">Call Notes</h2>
                <p class="text-sm text-gray-500 mt-1" id="notesContactLabel">Contact notes</p>
            </div>
            <button onclick="hideModal('notesModal')" class="p-2 rounded-xl hover:bg-gray-100">
                <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="flex-1 overflow-y-auto p-4 space-y-3" id="notesList">
            <div class="text-center text-gray-400 text-sm py-6">Loading notes...</div>
        </div>
        <div class="p-4 border-t border-gray-100 bg-gray-50 space-y-2">
            <textarea id="noteText" rows="3" placeholder="Write a call note..."
                class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-purple-500 resize-none"></textarea>
            <div class="flex gap-2">
                <button onclick="hideModal('notesModal')"
                    class="flex-1 py-3 bg-gray-200 rounded-xl font-medium hover:bg-gray-300 transition-colors">Close</button>
                <button onclick="saveNote()"
                    class="flex-1 py-3 bg-purple-500 text-white rounded-xl font-medium hover:bg-purple-600 transition-colors">Save Note</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (!$notesEnabled): ?>
<!-- Language Move Modal -->
<div id="moveModal"
    class="fixed inset-0 bg-black bg-opacity-60 z-50 hidden flex items-end sm:items-center justify-center"
    data-modal-backdrop>
    <div
        class="bg-white rounded-t-3xl sm:rounded-2xl w-full sm:max-w-md max-h-[85vh] overflow-hidden flex flex-col animate-slide-up">
        <div class="p-5 border-b border-gray-200">
            <h2 class="text-xl font-bold text-black">Move Contact</h2>
            <p class="text-sm text-gray-500 mt-1">Select the language this contact needs</p>
        </div>
        <div class="p-4 space-y-2 overflow-y-auto flex-1">
            <div class="mb-3">
                <label class="block text-sm font-medium text-gray-600 mb-1">Notes (optional)</label>
                <textarea id="moveNotes" rows="2"
                    class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-black focus:border-black resize-none"
                    placeholder="Why does this contact need to move?"></textarea>
            </div>
            <?php foreach ($languages as $lang): ?>
                <button onclick="submitMoveRequest(<?= $lang['id'] ?>)"
                    class="w-full p-3 rounded-xl border-2 border-gray-200 hover:border-blue-500 active:scale-[0.98] transition-all flex items-center gap-3 text-left group">
                    <div class="w-10 h-10 rounded-full bg-blue-50 flex items-center justify-center">
                        <span class="text-lg">🌐</span>
                    </div>
                    <span class="font-medium"><?= sanitize($lang['name']) ?></span>
                </button>
            <?php endforeach; ?>
        </div>
        <div class="p-4 border-t border-gray-100 bg-gray-50">
            <button onclick="hideModal('moveModal')"
                class="w-full py-3 bg-gray-200 rounded-xl font-medium hover:bg-gray-300 transition-colors">Cancel</button>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- WhatsApp Template Picker Modal -->
<div id="templatePickerModal"
    class="fixed inset-0 bg-black bg-opacity-60 z-50 hidden flex items-end sm:items-center justify-center"
    data-modal-backdrop>
    <div
        class="bg-white rounded-t-3xl sm:rounded-2xl w-full sm:max-w-md max-h-[85vh] overflow-hidden flex flex-col animate-slide-up">
        <div class="p-5 border-b border-gray-200">
            <h2 class="text-xl font-bold text-black">Choose Template</h2>
            <p class="text-sm text-gray-500 mt-1" id="templatePickerContact">Select message template</p>
        </div>
        <div class="p-4 space-y-3 overflow-y-auto flex-1" id="templatesList"></div>
        <div class="p-4 border-t border-gray-100 bg-gray-50">
            <button onclick="hideModal('templatePickerModal')"
                class="w-full py-3 bg-gray-200 rounded-xl font-medium hover:bg-gray-300 transition-colors">Cancel</button>
        </div>
    </div>
</div>

<!-- Hot Lead Modal -->
<div id="hotLeadModal"
    class="fixed inset-0 bg-black bg-opacity-60 z-50 hidden flex items-end sm:items-center justify-center"
    data-modal-backdrop>
    <div class="bg-white rounded-t-3xl sm:rounded-2xl w-full sm:max-w-md overflow-hidden flex flex-col animate-slide-up">
        <div class="p-5 border-b border-gray-200">
            <h2 class="text-xl font-bold text-black">Add Hot Lead</h2>
            <p class="text-sm text-gray-500 mt-1">Add a walk-in contact assigned to you</p>
        </div>
        <div class="p-5 space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Name *</label>
                <input type="text" id="hotLeadName" placeholder="Contact name"
                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-red-500 focus:border-red-500 text-base">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Phone *</label>
                <input type="tel" id="hotLeadPhone" placeholder="Phone number"
                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-red-500 focus:border-red-500 text-base font-mono">
            </div>
        </div>
        <div class="p-4 border-t border-gray-100 bg-gray-50 flex gap-3">
            <button onclick="hideModal('hotLeadModal')"
                class="flex-1 py-3 bg-gray-200 rounded-xl font-medium hover:bg-gray-300 transition-colors">Cancel</button>
            <button onclick="submitHotLead()" id="hotLeadSubmitBtn"
                class="flex-1 py-3 bg-red-500 text-white rounded-xl font-medium hover:bg-red-600 transition-colors">Add Hot Lead</button>
        </div>
    </div>
</div>

<style>
    @keyframes slide-up {
        from {
            transform: translateY(100%);
            opacity: 0;
        }

        to {
            transform: translateY(0);
            opacity: 1;
        }
    }

    .animate-slide-up {
        animation: slide-up 0.3s ease-out;
    }
</style>

<script>
    const notesEnabled = <?= $notesEnabled ? 'true' : 'false' ?>;
    let currentContactId = null;
    let currentContactPhone = null;
    let isAutoMode = false;
    let totalContacts = <?= $totalPending ?>;
    let completedContacts = 0;
    let timerInterval = null;

    // Auto mode toggle
    document.getElementById('autoModeToggle')?.addEventListener('change', function () {
        isAutoMode = this.checked;
        if (isAutoMode) {
            this.parentElement.parentElement.classList.add('bg-green-50', 'border-green-200');
            this.parentElement.parentElement.classList.remove('bg-white', 'border-gray-200');
        } else {
            this.parentElement.parentElement.classList.remove('bg-green-50', 'border-green-200');
            this.parentElement.parentElement.classList.add('bg-white', 'border-gray-200');
        }
    });

    function updateProgress() {
        const percent = totalContacts > 0 ? (completedContacts / totalContacts) * 100 : 0;
        document.getElementById('progressBar').style.width = percent + '%';
        document.getElementById('progressText').textContent = completedContacts + ' / ' + totalContacts;
        document.getElementById('pendingCount').textContent = totalContacts - completedContacts;
    }

    function initiateCall(contactId, phone) {
        currentContactId = contactId;
        currentContactPhone = phone;

        // Update UI
        const card = document.querySelector(`[data-id="${contactId}"]`);
        if (card) {
            card.querySelector('.state-call').classList.add('hidden');
            card.querySelector('.state-endcall').classList.remove('hidden');
            card.classList.add('border-red-500');
            card.classList.remove('border-black');
        }

        // Initiate call via tel: protocol
        window.location.href = `tel:${phone}`;
    }

    const whatsappTemplates = <?= json_encode($whatsappTemplates) ?>;
    let waPhone = null;
    let waName = null;

    function openTemplatePicker(phone, name) {
        waPhone = phone;
        waName = name;

        if (whatsappTemplates.length === 1) {
            sendWhatsAppWithTemplate(whatsappTemplates[0].message);
            return;
        }

        const list = document.getElementById('templatesList');
        document.getElementById('templatePickerContact').textContent = 'Sending to: ' + name;

        list.innerHTML = whatsappTemplates.map((tpl, index) => {
            const preview = tpl.message.replace(/{name}/g, name);
            return `<button onclick="sendTemplateByIndex(${index})" 
                    class="w-full p-4 rounded-xl border-2 ${tpl.is_default == 1 ? 'border-black' : 'border-gray-200'} hover:border-gray-900 active:scale-[0.98] transition-all text-left">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="font-bold">${tpl.name}</span>
                        ${tpl.is_default == 1 ? '<span class="px-2 py-0.5 bg-black text-white text-xs rounded-full">Default</span>' : ''}
                    </div>
                    <p class="text-sm text-gray-600 whitespace-pre-wrap line-clamp-3">${preview}</p>
                </button>`;
        }).join('');

        showModal('templatePickerModal');
    }

    function sendTemplateByIndex(index) {
        sendWhatsAppWithTemplate(whatsappTemplates[index].message);
    }

    function sendWhatsAppWithTemplate(templateMsg) {
        hideModal('templatePickerModal');

        let cleanPhone = waPhone.replace(/[^0-9]/g, '');
        if (cleanPhone.length === 10) cleanPhone = '91' + cleanPhone;

        const message = templateMsg.replace(/{name}/g, waName);
        const whatsappUrl = `https://wa.me/${cleanPhone}?text=${encodeURIComponent(message)}`;
        window.open(whatsappUrl, '_blank');
    }

    // Move request
    let moveContactId = null;

    function submitHotLead() {
        const name = document.getElementById('hotLeadName').value.trim();
        const phone = document.getElementById('hotLeadPhone').value.trim();

        if (!name || !phone) {
            showToast('Please enter both name and phone number.', 'error');
            return;
        }

        const btn = document.getElementById('hotLeadSubmitBtn');
        btn.disabled = true;
        btn.textContent = 'Adding...';

        fetch('<?= APP_URL ?>/api/contacts.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'add',
                name: name,
                phone: phone,
                assigned_to: <?= $userId ?>
            })
        }).then(r => r.json()).then(data => {
            if (data.success) {
                hideModal('hotLeadModal');
                document.getElementById('hotLeadName').value = '';
                document.getElementById('hotLeadPhone').value = '';
                showToast(name + ' added as a Hot Lead!');
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast(data.error || 'Could not add contact', 'error');
            }
        }).catch(() => showToast('Network error. Please try again.', 'error'))
        .finally(() => {
            btn.disabled = false;
            btn.textContent = 'Add Hot Lead';
        });
    }

    function openNotesModal(contactId) {
        currentContactId = contactId;
        document.getElementById('noteText').value = '';
        document.getElementById('notesList').innerHTML = '<div class="text-center text-gray-400 text-sm py-6">Loading notes...</div>';
        showModal('notesModal');
        fetch(`<?= APP_URL ?>/api/notes.php?action=list&contact_id=${contactId}`)
            .then(r => r.json())
            .then(data => {
                const list = document.getElementById('notesList');
                if (!data.notes || data.notes.length === 0) {
                    list.innerHTML = '<div class="text-center text-gray-400 text-sm py-6">No notes yet.</div>';
                    return;
                }
                list.innerHTML = data.notes.map(n => `
                    <div class="bg-gray-50 rounded-xl p-3 border border-gray-100">
                        <p class="text-sm text-gray-800 whitespace-pre-wrap">${n.note.replace(/</g,'&lt;').replace(/>/g,'&gt;')}</p>
                        <p class="text-xs text-gray-400 mt-1">${n.author} &middot; ${n.created_at}</p>
                    </div>`).join('');
            })
            .catch(() => {
                document.getElementById('notesList').innerHTML = '<div class="text-center text-red-400 text-sm py-6">Failed to load notes.</div>';
            });
    }

    function saveNote() {
        const note = document.getElementById('noteText').value.trim();
        if (!note) { showToast('Please write a note first', 'error'); return; }
        fetch('<?= APP_URL ?>/api/notes.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'add', contact_id: currentContactId, note })
        }).then(r => r.json()).then(data => {
            if (data.success) {
                document.getElementById('noteText').value = '';
                const list = document.getElementById('notesList');
                const n = data.note;
                const div = document.createElement('div');
                div.className = 'bg-gray-50 rounded-xl p-3 border border-gray-100';
                div.innerHTML = `<p class="text-sm text-gray-800 whitespace-pre-wrap">${n.note.replace(/</g,'&lt;').replace(/>/g,'&gt;')}</p><p class="text-xs text-gray-400 mt-1">${n.author} &middot; ${n.created_at}</p>`;
                if (list.querySelector('.text-gray-400')) list.innerHTML = '';
                list.prepend(div);
            } else {
                showToast(data.error || 'Could not save note', 'error');
            }
        });
    }

    function openMoveModal(contactId) {
        moveContactId = contactId;
        document.getElementById('moveNotes').value = '';
        showModal('moveModal');
    }

    function submitMoveRequest(languageId) {
        const notes = document.getElementById('moveNotes').value.trim();

        fetch('<?= APP_URL ?>/api/language.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'create_move_request',
                contact_id: moveContactId,
                requested_by: <?= $userId ?>,
                target_language_id: languageId,
                notes: notes
            })
        }).then(r => r.json()).then(data => {
            hideModal('moveModal');
            if (data.success) {
                showToast('Move request submitted! A manager will review it.');
            } else {
                showToast(data.error || 'Could not submit move request', 'error');
            }
        });
    }

    function endCall(contactId) {
        currentContactId = contactId;

        const card = document.querySelector(`[data-id="${contactId}"]`);
        const name = card?.dataset.name || 'Contact';

        // Reset comment field for new contact
        const commentField = document.getElementById('statusComment');
        if (commentField) commentField.value = '';

        document.getElementById('statusModalContact').textContent = name;
        showModal('statusModal');
    }

    function saveStatus(statusId) {
        const comment = document.getElementById('statusComment')?.value?.trim() || '';
        hideModal('statusModal');

        // Save via AJAX
        fetch('<?= APP_URL ?>/api/contacts.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'update_status',
                contact_id: currentContactId,
                status_id: statusId,
                user_id: <?= $userId ?>,
                notes: comment
            })
        }).then(response => response.json())
            .then(data => {
                if (data.success) {
                    completedContacts++;
                    updateProgress();

                    const card = document.querySelector(`[data-id="${currentContactId}"]`);
                    if (card) {
                        card.style.transition = 'all 0.4s cubic-bezier(0.4, 0, 0.2, 1)';
                        card.style.opacity = '0';
                        card.style.transform = 'translateX(-100%) scale(0.9)';
                        card.style.marginTop = '-' + card.offsetHeight + 'px';

                        setTimeout(() => {
                            card.remove();

                            if (isAutoMode) {
                                startAutoTimer();
                            } else {
                                activateNextCard();
                            }
                        }, 400);
                    }
                }
            });
    }

    function activateNextCard() {
        const cards = document.querySelectorAll('.calling-card');
        if (cards.length > 0) {
            cards.forEach((c, i) => {
                if (i === 0) {
                    c.classList.add('border-black', 'shadow-lg');
                    c.classList.remove('opacity-60', 'border-gray-200');
                } else {
                    c.classList.add('opacity-60', 'border-gray-200');
                    c.classList.remove('border-black', 'shadow-lg');
                }
            });
            cards[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
        } else if (completedContacts > 0) {
            // Only reload if we actually completed contacts during this session
            location.reload();
        }
    }

    function startAutoTimer() {
        const overlay = document.getElementById('timerOverlay');
        const timerNumber = document.getElementById('timerNumber');
        const timerCircle = document.getElementById('timerCircle');

        overlay.classList.remove('hidden');

        let seconds = 3;
        timerNumber.textContent = seconds;

        // Reset and animate circle
        timerCircle.style.transition = 'none';
        timerCircle.style.strokeDashoffset = '0';

        setTimeout(() => {
            timerCircle.style.transition = 'stroke-dashoffset 3s linear';
            timerCircle.style.strokeDashoffset = '452';
        }, 50);

        timerInterval = setInterval(() => {
            seconds--;
            timerNumber.textContent = seconds;

            if (seconds <= 0) {
                clearInterval(timerInterval);
                timerInterval = null;
                overlay.classList.add('hidden');

                const nextCard = document.querySelector('.calling-card');
                if (nextCard) {
                    activateNextCard();

                    // Auto-initiate call after a short delay
                    setTimeout(() => {
                        const contactId = nextCard.dataset.id;
                        const phone = nextCard.dataset.phone;
                        initiateCall(contactId, phone);
                    }, 500);
                } else if (completedContacts > 0) {
                    location.reload();
                }
            }
        }, 1000);
    }

    function cancelAutoNext() {
        if (timerInterval) {
            clearInterval(timerInterval);
            timerInterval = null;
        }
        document.getElementById('timerOverlay').classList.add('hidden');
        isAutoMode = false;
        const autoToggle = document.getElementById('autoModeToggle');
        if (autoToggle) autoToggle.checked = false;
        activateNextCard();
    }

    // Initialize first card as active (only if there are cards to activate)
    document.addEventListener('DOMContentLoaded', () => {
        if (document.querySelectorAll('.calling-card').length > 0) {
            activateNextCard();
        }
        // Auto-load all remaining cards in background
        loadAllCards();
    });

    // ========== LAZY LOADING FOR CALLING CARDS ==========
    let isLoadingMore = false;
    let hasMoreCards = <?= count($contacts) >= 20 ? 'true' : 'false' ?>;
    let nextCursor = <?= !empty($contacts) ? end($contacts)['id'] : 'null' ?>;
    let cardsLoadedCount = <?= count($contacts) ?>;

    async function loadAllCards() {
        let retries = 0;
        while (hasMoreCards && retries < 5) {
            const before = cardsLoadedCount;
            await loadMoreCards();
            if (cardsLoadedCount === before) {
                retries++;
            } else {
                retries = 0;
            }
        }
    }

    // Load more cards when needed
    function checkAndLoadMore() {
        if (hasMoreCards && !isLoadingMore) {
            loadMoreCards();
        }
    }

    async function loadMoreCards() {
        if (isLoadingMore || !hasMoreCards) return;

        isLoadingMore = true;
        console.log('Loading more cards... cursor:', nextCursor);

        try {
            const response = await fetch(`<?= APP_URL ?>/api/calling_cards.php?cursor=${nextCursor || ''}&limit=20`);
            const result = await response.json();

            if (result.success && result.data.contacts.length > 0) {
                const cardsContainer = document.getElementById('callingCards');
                const statuses = <?= json_encode($statuses) ?>;
                const languages = <?= json_encode($languages) ?>;

                result.data.contacts.forEach((contact, index) => {
                    const cardHTML = createCardHTML(contact, cardsLoadedCount + index);
                    cardsContainer.insertAdjacentHTML('beforeend', cardHTML);
                });

                cardsLoadedCount += result.data.contacts.length;
                nextCursor = result.data.pagination.next_cursor;
                hasMoreCards = result.data.pagination.has_more;
                totalContacts = result.data.pagination.total_count;

                console.log(`Loaded ${result.data.contacts.length} more cards. Total loaded: ${cardsLoadedCount}`);
            } else {
                hasMoreCards = false;
            }
        } catch (error) {
            console.error('Error loading more cards:', error);
        } finally {
            isLoadingMore = false;
        }
    }

    function createCardHTML(contact, index) {
        const initial = contact.name.charAt(0).toUpperCase();
        const notesHTML = contact.notes ? `<p class="text-sm text-gray-400 mt-2 line-clamp-2">${escapeHtml(contact.notes)}</p>` : '';

        return `<div class="calling-card bg-white rounded-2xl border-2 border-gray-200 overflow-hidden transition-all duration-300 opacity-60"
            data-id="${contact.id}" data-phone="${escapeHtml(contact.phone)}"
            data-name="${escapeHtml(contact.name)}" data-index="${index}">

            <div class="p-5 sm:p-6">
                <div class="flex items-start gap-4">
                    <div class="w-14 h-14 sm:w-16 sm:h-16 rounded-full bg-gradient-to-br from-gray-800 to-black text-white flex items-center justify-center font-bold text-xl sm:text-2xl flex-shrink-0 shadow-md">
                        ${initial}
                    </div>
                    <div class="flex-1 min-w-0">
                        <h2 class="text-xl sm:text-2xl font-bold text-black truncate">${escapeHtml(contact.name)}</h2>
                        <p class="text-lg sm:text-xl text-gray-600 font-mono mt-1">${escapeHtml(contact.phone)}</p>
                        ${notesHTML}
                    </div>
                    <div class="bg-gray-100 px-3 py-1 rounded-full">
                        <span class="text-sm font-bold text-gray-600">${index + 1}/${totalContacts}</span>
                    </div>
                </div>
            </div>

            <div class="card-actions border-t border-gray-100 p-4 bg-gray-50">
                <div class="state-call flex gap-2">
                    <button onclick="initiateCall(${contact.id}, '${escapeHtml(contact.phone)}')"
                        class="flex-1 py-4 bg-green-500 text-white rounded-xl font-bold text-base flex items-center justify-center gap-2 hover:bg-green-600 active:scale-[0.98] transition-all pulse-call">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                        </svg>
                        CALL
                    </button>
                    <button onclick="openTemplatePicker('${escapeHtml(contact.phone)}', '${escapeHtml(contact.name)}')"
                        class="flex-1 py-4 bg-emerald-600 text-white rounded-xl font-bold text-base flex items-center justify-center gap-2 hover:bg-emerald-700 active:scale-[0.98] transition-all">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z" />
                        </svg>
                        WHATSAPP
                    </button>
                    ${notesEnabled ? `
                    <button onclick="openNotesModal(${contact.id})"
                        class="py-4 px-3 bg-purple-500 text-white rounded-xl font-bold text-base flex items-center justify-center gap-1 hover:bg-purple-600 active:scale-[0.98] transition-all" title="Notes">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                        NOTES
                    </button>` : `
                    <button onclick="openMoveModal(${contact.id})"
                        class="py-4 px-3 bg-orange-500 text-white rounded-xl font-bold text-base flex items-center justify-center gap-1 hover:bg-orange-600 active:scale-[0.98] transition-all" title="Move to another language">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129" />
                        </svg>
                        MOVE
                    </button>`}
                </div>
                <div class="state-endcall hidden">
                    <div class="flex gap-2">
                        <button onclick="endCall(${contact.id})"
                            class="flex-1 py-4 bg-red-500 text-white rounded-xl font-bold text-lg flex items-center justify-center gap-3 hover:bg-red-600 active:scale-[0.98] transition-all">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 8l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2M5 3a2 2 0 00-2 2v1c0 8.284 6.716 15 15 15h1a2 2 0 002-2v-3.28a1 1 0 00-.684-.948l-4.493-1.498a1 1 0 00-1.21.502l-1.13 2.257a11.042 11.042 0 01-5.516-5.517l2.257-1.128a1 1 0 00.502-1.21L9.228 3.683A1 1 0 008.28 3H5z" />
                            </svg>
                            CALL ENDED - SELECT STATUS
                        </button>
                        <button onclick="openTemplatePicker('${contact.phone}', '${contact.name}')"
                            class="py-4 px-3 bg-emerald-600 text-white rounded-xl font-bold text-base flex items-center justify-center gap-1 hover:bg-emerald-700 active:scale-[0.98] transition-all" title="Send WhatsApp">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                            </svg>
                            WA
                        </button>
                        ${notesEnabled ? `
                        <button onclick="openNotesModal(${contact.id})"
                            class="py-4 px-3 bg-purple-500 text-white rounded-xl font-bold text-base flex items-center justify-center gap-1 hover:bg-purple-600 active:scale-[0.98] transition-all" title="Notes">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                            NOTES
                        </button>` : `
                        <button onclick="openMoveModal(${contact.id})"
                            class="py-4 px-3 bg-orange-500 text-white rounded-xl font-bold text-base flex items-center justify-center gap-1 hover:bg-orange-600 active:scale-[0.98] transition-all" title="Language Transfer">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129" />
                            </svg>
                            MOVE
                        </button>`}
                    </div>
                </div>
            </div>
        </div>`;
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Hook into saveStatus to check if we need to load more
    const originalSaveStatus = saveStatus;
    saveStatus = function(statusId) {
        originalSaveStatus(statusId);
        // Check after card is removed
        setTimeout(() => checkAndLoadMore(), 500);
    };

</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>