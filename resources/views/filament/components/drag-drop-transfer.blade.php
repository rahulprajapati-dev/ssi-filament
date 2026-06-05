<div class="fi-fo-drag-drop-transfer"
     style="display:flex; flex-direction:column; gap:8px;"
     x-data="{
        available: [],
        selected: @entangle($getStatePath()) ?? [],
        searchQuery: '',
        isDraggingOverAvailable: false,
        isDraggingOverSelected: false,
        draggedItem: null,
        draggedSource: null,
        draggedIndex: null,
        source: '{{ $source ?? 'repeater' }}',
        dependsOn: '{{ $dependsOn ?? '' }}',

        init() {
            // Normalise selected to an array
            if (!this.selected) {
                this.selected = [];
            } else if (typeof this.selected === 'string') {
                this.selected = this.selected.split(',').map(s => s.trim()).filter(Boolean);
            } else if (!Array.isArray(this.selected)) {
                this.selected = Object.values(this.selected);
            }

            if (this.source === 'module_fields') {
                // ── Module-fields mode: fetch from Livewire method on module_id change ──
                this.$nextTick(async () => {
                    const mid = this.$wire.data?.[this.dependsOn];
                    if (mid) await this.syncFromModuleId(mid);
                });

                this.$watch('$wire.data.' + this.dependsOn, async (newVal) => {
                    // When module changes, reset selected and reload available
                    this.selected = [];
                    await this.syncFromModuleId(newVal);
                });
            } else {
                // ── Repeater mode: sync available from repeater items ──
                this.$nextTick(() => {
                    this.syncAvailable(this.$wire.data ?? {});
                });

                this.$watch('$wire.data', (newData) => {
                    this.syncAvailable(newData ?? {});
                }, { deep: true });
            }
        },

        async syncFromModuleId(moduleId) {
            if (!moduleId) {
                this.available = [];
                return;
            }
            try {
                const allFields = await this.$wire.call('getModuleFields', parseInt(moduleId));
                this.available = (allFields || []).filter(f => !this.selected.includes(f));
            } catch (e) {
                this.available = [];
            }
        },

        syncAvailable(data) {
            const repeaterName = '{{ $repeaterName }}';
            const repeaterItems = data[repeaterName] || [];
            if (!Array.isArray(repeaterItems)) {
                this.available = [];
                return;
            }

            const allFields = repeaterItems
                .map(item => item.field_name)
                .filter(Boolean);

            this.available = allFields.filter(field => !this.selected.includes(field));
        },

        get filteredAvailable() {
            if (!this.searchQuery) return this.available;
            const query = this.searchQuery.toLowerCase();
            return this.available.filter(item => item.toLowerCase().includes(query));
        },

        dragStart(event, item, source, index = null) {
            this.draggedItem = item;
            this.draggedSource = source;
            this.draggedIndex = index;
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', item);
        },

        dragOver(event, target) {
            event.preventDefault();
            if (target === 'available') {
                this.isDraggingOverAvailable = true;
                this.isDraggingOverSelected = false;
            } else if (target === 'selected') {
                this.isDraggingOverSelected = true;
                this.isDraggingOverAvailable = false;
            }
        },

        dragLeave() {
            this.isDraggingOverAvailable = false;
            this.isDraggingOverSelected = false;
        },

        drop(event, target, targetIndex = null) {
            event.preventDefault();
            this.isDraggingOverAvailable = false;
            this.isDraggingOverSelected = false;

            if (!this.draggedItem) return;

            const item = this.draggedItem;
            const source = this.draggedSource;
            const sourceIndex = this.draggedIndex;

            this.draggedItem = null;
            this.draggedSource = null;
            this.draggedIndex = null;

            if (source === target) {
                if (target === 'selected' && sourceIndex !== null && targetIndex !== null && sourceIndex !== targetIndex) {
                    const temp = [...this.selected];
                    temp.splice(sourceIndex, 1);
                    temp.splice(targetIndex, 0, item);
                    this.selected = temp;
                }
                return;
            }

            if (target === 'selected') {
                this.available = this.available.filter(i => i !== item);
                if (targetIndex !== null) {
                    const temp = [...this.selected];
                    temp.splice(targetIndex, 0, item);
                    this.selected = temp;
                } else {
                    this.selected = [...this.selected, item];
                }
            } else if (target === 'available') {
                this.selected = this.selected.filter(i => i !== item);
                if (!this.available.includes(item)) {
                    this.available = [...this.available, item];
                }
            }
        },

        moveToSelected(item) {
            this.available = this.available.filter(i => i !== item);
            if (!this.selected.includes(item)) {
                this.selected = [...this.selected, item];
            }
        },

        moveToAvailable(item) {
            this.selected = this.selected.filter(i => i !== item);
            if (!this.available.includes(item)) {
                this.available = [...this.available, item];
            }
        },

        moveAllToSelected() {
            this.selected = [...this.selected, ...this.available];
            this.available = [];
        },

        moveAllToAvailable() {
            this.available = [...this.available, ...this.selected];
            this.selected = [];
        }
     }">

    {{-- Label --}}
    @if ($getLabel())
        <label class="fi-fo-field-wrp-label inline-flex items-center gap-x-1 text-sm font-medium leading-6 text-gray-950 dark:text-white">
            {{ $getLabel() }}
        </label>
    @endif

    {{-- Module-fields mode: hint when no module selected --}}
    @if (($source ?? 'repeater') === 'module_fields')
        <p x-show="available.length === 0 && selected.length === 0"
           style="font-size:12px; color:#9ca3af; margin:0;">
            Select a Module above to load its fields.
        </p>
    @endif

    <div style="display:grid; grid-template-columns: 1fr 48px 1fr; gap:12px; align-items:start;">

        {{-- ── Available Fields ── --}}
        <div style="display:flex; flex-direction:column; height:288px; border:1px solid #e5e7eb; border-radius:12px; background:#fff; overflow:hidden; transition:box-shadow .15s;"
             :style="isDraggingOverAvailable ? 'box-shadow:0 0 0 2px #f59e0b; border-color:#f59e0b;' : ''"
             @dragover="dragOver($event, 'available')"
             @dragleave="dragLeave"
             @drop="drop($event, 'available')">

            {{-- Header --}}
            <div style="padding:10px 14px; background:#f9fafb; border-bottom:1px solid #f3f4f6; display:flex; justify-content:space-between; align-items:center;">
                <span style="font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:#6b7280;">Available Fields</span>
                <span style="padding:1px 8px; font-size:11px; font-weight:700; background:#f3f4f6; color:#6b7280; border-radius:9999px;" x-text="available.length"></span>
            </div>

            {{-- Search --}}
            <div style="padding:8px; border-bottom:1px solid #f3f4f6;">
                <input type="text"
                       x-model="searchQuery"
                       placeholder="Filter available fields..."
                       style="width:100%; box-sizing:border-box; font-size:12px; padding:5px 10px; border:1px solid #e5e7eb; border-radius:8px; background:#f9fafb; outline:none; color:#374151;" />
            </div>

            {{-- Items --}}
            <div style="flex:1; overflow-y:auto; padding:8px; display:flex; flex-direction:column; gap:4px;">
                <template x-for="item in filteredAvailable" :key="item">
                    <div draggable="true"
                         @dragstart="dragStart($event, item, 'available')"
                         @dblclick="moveToSelected(item)"
                         style="display:flex; align-items:center; justify-content:space-between; padding:7px 10px; border-radius:8px; background:#f9fafb; border:1px solid #f3f4f6; cursor:grab; transition:background .1s, border-color .1s;"
                         @mouseenter="$el.style.background='#fffbeb'; $el.style.borderColor='#fde68a';"
                         @mouseleave="$el.style.background='#f9fafb'; $el.style.borderColor='#f3f4f6';">
                        <div style="display:flex; align-items:center; gap:8px; min-width:0;">
                            <svg style="width:14px;height:14px;color:#9ca3af;flex-shrink:0;" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                            </svg>
                            <span style="font-size:12px; font-weight:500; color:#374151; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" x-text="item"></span>
                        </div>
                        <button type="button"
                                @click="moveToSelected(item)"
                                style="padding:4px; border:none; background:transparent; cursor:pointer; color:#d97706; border-radius:4px; opacity:0; transition:opacity .1s;"
                                @mouseenter="$el.style.opacity='1';"
                                @mouseleave="$el.style.opacity='0';"
                                x-init="$el.closest('[draggable]').addEventListener('mouseenter', () => $el.style.opacity='1'); $el.closest('[draggable]').addEventListener('mouseleave', () => $el.style.opacity='0');">
                            <svg style="width:13px;height:13px;" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>
                        </button>
                    </div>
                </template>

                {{-- Empty state --}}
                <div x-show="filteredAvailable.length === 0"
                     style="flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center; padding:16px; text-align:center;">
                    <svg style="width:28px; height:28px; color:#d1d5db; margin-bottom:6px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
                    </svg>
                    <span style="font-size:11px; color:#9ca3af;">No fields available</span>
                </div>
            </div>
        </div>

        {{-- ── Transfer Controls ── --}}
        <div style="display:flex; flex-direction:column; align-items:center; justify-content:center; gap:6px; padding-top:48px;">
            <button type="button"
                    @click="moveAllToSelected"
                    title="Move all to selected"
                    style="padding:7px; border:1px solid #e5e7eb; border-radius:8px; background:#f9fafb; cursor:pointer; color:#6b7280; transition:background .1s, color .1s;"
                    @mouseenter="$el.style.background='#fffbeb'; $el.style.color='#d97706'; $el.style.borderColor='#fde68a';"
                    @mouseleave="$el.style.background='#f9fafb'; $el.style.color='#6b7280'; $el.style.borderColor='#e5e7eb';">
                <svg style="width:14px;height:14px;" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 4.5l7.5 7.5-7.5 7.5m-6-15l7.5 7.5-7.5 7.5" />
                </svg>
            </button>
            <button type="button"
                    @click="moveAllToAvailable"
                    title="Move all to available"
                    style="padding:7px; border:1px solid #e5e7eb; border-radius:8px; background:#f9fafb; cursor:pointer; color:#6b7280; transition:background .1s, color .1s;"
                    @mouseenter="$el.style.background='#fffbeb'; $el.style.color='#d97706'; $el.style.borderColor='#fde68a';"
                    @mouseleave="$el.style.background='#f9fafb'; $el.style.color='#6b7280'; $el.style.borderColor='#e5e7eb';">
                <svg style="width:14px;height:14px;" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M18.75 19.5l-7.5-7.5 7.5-7.5m-6 15L5.25 12l7.5-7.5" />
                </svg>
            </button>
        </div>

        {{-- ── Selected Fields ── --}}
        <div style="display:flex; flex-direction:column; height:288px; border:1px solid #e5e7eb; border-radius:12px; background:#fff; overflow:hidden; transition:box-shadow .15s;"
             :style="isDraggingOverSelected ? 'box-shadow:0 0 0 2px #f59e0b; border-color:#f59e0b;' : ''"
             @dragover="dragOver($event, 'selected')"
             @dragleave="dragLeave"
             @drop="drop($event, 'selected')">

            {{-- Header --}}
            <div style="padding:10px 14px; background:#f9fafb; border-bottom:1px solid #f3f4f6; display:flex; justify-content:space-between; align-items:center;">
                <span style="font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:#6b7280;">Selected Fields</span>
                <span style="padding:1px 8px; font-size:11px; font-weight:700; background:#fef3c7; color:#d97706; border-radius:9999px;" x-text="selected.length"></span>
            </div>

            {{-- Items --}}
            <div style="flex:1; overflow-y:auto; padding:8px; display:flex; flex-direction:column; gap:4px;">
                <template x-for="(item, idx) in selected" :key="item">
                    <div draggable="true"
                         @dragstart="dragStart($event, item, 'selected', idx)"
                         @dragover.prevent="dragOver($event, 'selected')"
                         @drop.stop="drop($event, 'selected', idx)"
                         @dblclick="moveToAvailable(item)"
                         style="display:flex; align-items:center; justify-content:space-between; padding:7px 10px; border-radius:8px; background:#fffbeb; border:1px solid #fde68a; cursor:grab; transition:background .1s, border-color .1s;"
                         @mouseenter="$el.style.background='#fef3c7';"
                         @mouseleave="$el.style.background='#fffbeb';">
                        <div style="display:flex; align-items:center; gap:8px; min-width:0;">
                            <svg style="width:14px;height:14px;color:#9ca3af;flex-shrink:0;" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                            </svg>
                            <span style="font-size:12px; font-weight:500; color:#374151; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" x-text="item"></span>
                        </div>
                        <div style="display:flex; align-items:center; gap:6px;">
                            <span style="font-size:10px; font-weight:700; color:#d97706;" x-text="idx + 1"></span>
                            <button type="button"
                                    @click="moveToAvailable(item)"
                                    style="padding:4px; border:none; background:transparent; cursor:pointer; color:#ef4444; border-radius:4px; opacity:0; transition:opacity .1s;"
                                    x-init="$el.closest('[draggable]').addEventListener('mouseenter', () => $el.style.opacity='1'); $el.closest('[draggable]').addEventListener('mouseleave', () => $el.style.opacity='0');">
                                <svg style="width:13px;height:13px;" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 12h-15" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </template>

                {{-- Empty state --}}
                <div x-show="selected.length === 0"
                     style="flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center; padding:16px; text-align:center;">
                    <svg style="width:28px; height:28px; color:#d1d5db; margin-bottom:6px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    </svg>
                    <span style="font-size:11px; color:#9ca3af;">Drag fields here</span>
                </div>
            </div>
        </div>

    </div>
</div>
