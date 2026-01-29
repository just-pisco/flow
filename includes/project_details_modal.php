<!-- Project Details Modal -->
<div id="projectDetailsModal" class="fixed inset-0 z-50 flex items-center justify-center hidden"
    aria-labelledby="modal-title-details" role="dialog" aria-modal="true">
    <div class="absolute inset-0 bg-gray-900/75 backdrop-blur-sm transition-opacity opacity-0"
        id="projectDetailsBackdrop" onclick="closeProjectDetailsModal()"></div>
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-2xl relative z-10 transform transition-all scale-95 opacity-0 flex flex-col max-h-[90vh]"
        id="projectDetailsContent">

        <!-- Header -->
        <div class="p-6 border-b border-slate-100 flex justify-between items-center">
            <h3 class="text-xl font-bold text-slate-900">Dettagli Progetto</h3>
            <button onclick="closeProjectDetailsModal()" class="text-slate-400 hover:text-slate-600 transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <!-- Tabs -->
        <div class="px-6 border-b border-slate-100">
            <div class="flex gap-6">
                <button onclick="switchProjectTab('info')" id="tabBtn-info"
                    class="py-3 text-sm font-medium border-b-2 border-indigo-600 text-indigo-600 transition-colors">
                    Info & Descrizione
                </button>
                <button onclick="switchProjectTab('members')" id="tabBtn-members"
                    class="py-3 text-sm font-medium border-b-2 border-transparent text-slate-500 hover:text-slate-700 transition-colors">
                    Membri
                </button>
                <button onclick="switchProjectTab('attachments')" id="tabBtn-attachments"
                    class="py-3 text-sm font-medium border-b-2 border-transparent text-slate-500 hover:text-slate-700 transition-colors">
                    Allegati
                </button>
            </div>
        </div>

        <!-- Body -->
        <div class="p-6 overflow-y-auto flex-1 bg-slate-50">

            <!-- Tab: Info -->
            <div id="tab-info" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Nome Progetto</label>
                    <input type="text" id="detailProjectName"
                        class="w-full border border-slate-300 rounded-lg p-3 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Descrizione</label>
                    <textarea id="detailProjectDesc" rows="6"
                        class="w-full border border-slate-300 rounded-lg p-3 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        placeholder="Descrivi il progetto, obiettivi, link utili..."></textarea>
                </div>
                <div class="flex justify-end pt-2">
                    <button onclick="saveProjectDetails()"
                        class="bg-indigo-600 text-white px-4 py-2 rounded-lg font-bold hover:bg-indigo-700 transition shadow-sm"
                        id="btnSaveDetails">
                        Salva Modifiche
                    </button>
                </div>
            </div>

            <!-- Tab: Members -->
            <div id="tab-members" class="hidden space-y-6">
                <!-- Add Member -->
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Aggiungi
                        Membro</label>
                    <div class="relative">
                        <input type="text" id="detailMemberSearch" placeholder="Cerca username..."
                            onkeyup="searchUsersDetails(this.value)"
                            class="w-full border border-slate-200 rounded-lg p-3 text-sm focus:ring-2 focus:ring-indigo-500 outline-none">

                        <div id="detailMemberResults"
                            class="absolute left-0 right-0 top-full mt-1 bg-white border border-slate-100 shadow-xl rounded-lg hidden z-20 max-h-40 overflow-y-auto">
                        </div>
                    </div>
                </div>

                <!-- List -->
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Membri
                        Attuali</label>
                    <div id="detailMembersList" class="space-y-2">
                        <p class="text-sm text-slate-400 italic">Caricamento...</p>
                    </div>
                </div>
            </div>

            <!-- Tab: Attachments -->
            <div id="tab-attachments" class="hidden space-y-6">
                <!-- List -->
                <div id="attachmentsList" class="space-y-3">
                    <p class="text-sm text-slate-400 italic">Nessun allegato.</p>
                </div>

                <!-- Add Form -->
                <div class="mt-4 flex justify-center">
                    <button onclick="openDrivePicker()"
                        class="flex items-center gap-2 bg-indigo-50 text-indigo-700 hover:bg-indigo-100 hover:text-indigo-800 px-4 py-2 rounded-lg font-bold transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M10.5 3.75a6 6 0 0 0-5.98 6.496A5.25 5.25 0 0 0 6.75 20.25H18a4.5 4.5 0 0 0 2.206-8.423 3.75 3.75 0 0 0-4.133-4.303A6.001 6.001 0 0 0 10.5 3.75Zm2.03 5.47a.75.75 0 0 0-1.06 0l-3 3a.75.75 0 1 0 1.06 1.06l1.72-1.72v4.94a.75.75 0 0 0 1.5 0v-4.94l1.72 1.72a.75.75 0 1 0 1.06-1.06l-3-3Z"
                                clip-rule="evenodd" />
                        </svg>
                        Carica File
                    </button>
                </div>
            </div>
        </div>

    </div>
</div>
<!-- Remove Member Confirmation Modal -->
<div id="removeMemberModal" class="fixed inset-0 z-[80] flex items-center justify-center hidden" aria-modal="true">
    <div class="absolute inset-0 bg-gray-900/75 backdrop-blur-sm transition-opacity opacity-0" id="removeMemberBackdrop"
        onclick="closeRemoveMemberModal()"></div>

    <div class="bg-white rounded-xl shadow-2xl w-full max-w-sm relative z-10 transform transition-all scale-95 opacity-0 p-6 text-center"
        id="removeMemberContent">

        <div class="w-12 h-12 rounded-full bg-red-100 flex items-center justify-center mx-auto mb-4">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
        </div>

        <h3 class="text-lg font-bold text-slate-800 mb-2">Rimuovere Membro?</h3>
        <p class="text-sm text-slate-500 mb-6">L'utente perderà l'accesso al progetto e ai task assegnati.</p>

        <div class="flex gap-3 justify-center">
            <button onclick="closeRemoveMemberModal()"
                class="px-4 py-2 rounded-lg text-slate-700 hover:bg-slate-100 font-medium transition-colors">
                Annulla
            </button>
            <button onclick="executeRemoveMember()"
                class="px-4 py-2 rounded-lg bg-red-600 text-white hover:bg-red-700 font-bold shadow-md transition-all">
                Rimuovi
            </button>
        </div>
    </div>
</div>