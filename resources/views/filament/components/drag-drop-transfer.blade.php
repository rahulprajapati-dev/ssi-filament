<div class="fi-fo-drag-drop-transfer" wire:ignore
     data-owner-module-id="{{ $ownerModuleId ?? '' }}"
     data-owner-module-fields="{{ json_encode($ownerModuleFields ?? null) }}"
     x-data="{
        available: [],
        fieldLabels: {},
        selected: @entangle($getStatePath()) ?? [],
        searchQuery: '',
        isDraggingOverAvailable: false,
        draggedItem: null,
        draggedSource: null,
        draggedIndex: null,
        source: '{{ $source ?? 'repeater' }}',
        dependsOn: '{{ $dependsOn ?? '' }}',
        ownerModuleId: null,
        ownerModuleFields: null,
        draggedSectionIndex: null,
        isDraggingSection: false,
        isListView: false,
        activeTabIndices: {},

        init() {
            const rawId = this.$el.dataset.ownerModuleId;
            this.ownerModuleId = rawId ? parseInt(rawId) : null;
            try { this.ownerModuleFields = JSON.parse(this.$el.dataset.ownerModuleFields || 'null'); } catch(e) {}

            this.normalizeSelected();

            const currentType = this.$wire.data?.layout_type;
            this.isListView = (currentType === 'list');

            this.$nextTick(() => {
                const innerType = this.$wire.data?.layout_type;
                this.isListView = (innerType === 'list');
            });

            this.$watch('$wire.data.layout_type', async (newType) => {
                this.isListView = (newType === 'list');
                if (this.source === 'module_fields') {
                    let mid = this.$wire.data?.[this.dependsOn] || this.ownerModuleId || null;
                    if (mid) await this.syncFromModuleId(mid, true);
                }
            });

            if (this.source === 'module_fields') {
                this.$nextTick(async () => {
                    let mid = this.$wire.data?.[this.dependsOn] || this.ownerModuleId || null;
                    if (mid) await this.syncFromModuleId(mid);
                });

                this.$watch('$wire.data.' + this.dependsOn, async (newVal) => {
                    this.selected = [];
                    await this.syncFromModuleId(newVal);
                });
            } else {
                this.$nextTick(() => {
                    this.syncAvailable(this.$wire.data ?? {});
                });
                this.$watch('$wire.data', (newData) => {
                    this.syncAvailable(newData ?? {});
                }, { deep: true });
            }
        },

        normalizeSelected() {
            if (!this.selected) this.selected = [];
            if (Array.isArray(this.selected)) {
                if (this.selected.length > 0 && typeof this.selected[0] === 'string') {
                    // Legacy flat array of field strings → single section
                    this.selected = [{type: 'section', title: 'Default Section', columns: 2, fields: [...this.selected]}];
                } else {
                    // Ensure every item has a type (backward compat: no 'type' key = section)
                    this.selected = this.selected.map(s => { if (!s.type) s.type = 'section'; return s; });
                }
            } else {
                this.selected = [];
            }
            if (this.selected.length === 0) this.addSection();
        },

        // Returns all fields (flat) from a container regardless of type
        getAllFieldsFromContainer(container) {
            if (!container) return [];
            if ((container.type || 'section') === 'tabs') {
                return (container.tabs || []).flatMap(t => t.fields || []);
            }
            return container.fields || [];
        },

        async syncFromModuleId(moduleId, forceWire = false) {
            if (!moduleId) { this.available = []; return; }
            try {
                let allFields;
                const layoutType = this.$wire.data?.layout_type || null;
                if (!forceWire && this.ownerModuleFields !== null && String(moduleId) === String(this.ownerModuleId)) {
                    allFields = this.ownerModuleFields;
                } else {
                    allFields = await this.$wire.call('getModuleFields', parseInt(moduleId), layoutType);
                }

                const labels = {};
                const names = (allFields || []).map(f => {
                    if (typeof f === 'object' && f.field_name) {
                        labels[f.field_name] = f.label || f.field_name;
                        return f.field_name;
                    }
                    return f;
                });
                this.fieldLabels = Object.assign({}, this.fieldLabels, labels);

                const selectedFieldsSet = new Set();
                this.selected.forEach(c => { this.getAllFieldsFromContainer(c).forEach(f => selectedFieldsSet.add(f)); });
                this.available = names.filter(f => !selectedFieldsSet.has(f));
            } catch (e) {
                this.available = [];
            }
        },

        syncAvailable(data) {
            const repeaterName = '{{ $repeaterName }}';
            const repeaterItems = data[repeaterName] || [];
            if (!Array.isArray(repeaterItems)) { this.available = []; return; }

            const labels = {};
            const allFields = repeaterItems
                .filter(item => item.field_name)
                .map(item => { labels[item.field_name] = item.label || item.field_name; return item.field_name; });
            this.fieldLabels = Object.assign({}, this.fieldLabels, labels);

            const selectedFieldsSet = new Set();
            this.selected.forEach(c => { this.getAllFieldsFromContainer(c).forEach(f => selectedFieldsSet.add(f)); });
            this.available = allFields.filter(field => !selectedFieldsSet.has(field));
        },

        get filteredAvailable() {
            if (!this.searchQuery) return this.available;
            const query = this.searchQuery.toLowerCase();
            return this.available.filter(item => {
                const label = this.fieldLabels[item] || item;
                return label.toLowerCase().includes(query) || item.toLowerCase().includes(query);
            });
        },

        // ── Container add methods ──────────────────────────────────────────────

        addSection() {
            this.selected.push({type: 'section', title: 'Section ' + (this.selected.length + 1), columns: 2, fields: []});
            this.updateState();
        },

        addGrid() {
            this.selected.push({type: 'grid', columns: 2, fields: []});
            this.updateState();
        },

        addTabs() {
            this.selected.push({type: 'tabs', columns: 2, title: '', tabs: [{label: 'Tab 1', fields: []}, {label: 'Tab 2', fields: []}]});
            this.updateState();
        },

        addTab(containerIdx) {
            const c = this.selected[containerIdx];
            if (!c.tabs) c.tabs = [];
            c.tabs.push({label: 'Tab ' + (c.tabs.length + 1), fields: []});
            this.updateState();
        },

        removeTab(containerIdx, tabIdx) {
            const c = this.selected[containerIdx];
            const tab = c.tabs[tabIdx] || {};
            (tab.fields || []).forEach(f => { if (!this.available.includes(f)) this.available.push(f); });
            c.tabs.splice(tabIdx, 1);
            const cur = this.activeTabIndices[containerIdx] ?? 0;
            if (cur >= c.tabs.length) this.activeTabIndices[containerIdx] = Math.max(0, c.tabs.length - 1);
            this.updateState();
        },

        getActiveTabIdx(containerIdx) {
            return this.activeTabIndices[containerIdx] ?? 0;
        },

        setActiveTab(containerIdx, tabIdx) {
            this.activeTabIndices[containerIdx] = tabIdx;
        },

        removeSection(index) {
            const allFields = this.getAllFieldsFromContainer(this.selected[index]);
            if (this.selected.length <= 1) {
                allFields.forEach(f => { if (!this.available.includes(f)) this.available.push(f); });
                this.selected[index] = {type: 'section', title: 'Default Section', columns: 2, fields: []};
                this.updateState();
                return;
            }
            allFields.forEach(f => { if (!this.available.includes(f)) this.available.push(f); });
            this.selected.splice(index, 1);
            this.updateState();
        },

        updateState() {
            this.selected = JSON.parse(JSON.stringify(this.selected));
        },

        // ── Drag helpers ───────────────────────────────────────────────────────

        dragStart(event, item, source, index = null) {
            this.isDraggingSection = false;
            this.draggedItem = item;
            this.draggedSource = source;
            this.draggedIndex = index;
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', item);
        },

        dragStartSection(event, index) {
            this.isDraggingSection = true;
            this.draggedSectionIndex = index;
            event.dataTransfer.effectAllowed = 'move';
        },

        dragOverSection(event, index) { event.preventDefault(); },

        dragEnd(event) {
            this.draggedItem = null;
            this.draggedSource = null;
            this.draggedIndex = null;
            this.isDraggingSection = false;
            this.draggedSectionIndex = null;
        },

        dropSection(event, targetIndex) {
            event.preventDefault();
            if (!this.isDraggingSection || this.draggedSectionIndex === null || this.draggedSectionIndex === targetIndex) return;
            const temp = [...this.selected];
            const [moved] = temp.splice(this.draggedSectionIndex, 1);
            temp.splice(targetIndex, 0, moved);
            this.selected = temp;
            this.draggedSectionIndex = null;
            this.isDraggingSection = false;
            this.updateState();
        },

        dragOver(event, target) {
            event.preventDefault();
            if (target === 'available') this.isDraggingOverAvailable = true;
        },

        dragLeave() { this.isDraggingOverAvailable = false; },

        drop(event, target, targetIndex = null, targetTabIdx = null) {
            event.preventDefault();
            this.isDraggingOverAvailable = false;
            if (this.isDraggingSection) return;
            if (!this.draggedItem) return;

            const item        = this.draggedItem;
            const source      = this.draggedSource;
            const sourceIndex = this.draggedIndex;

            this.draggedItem = null;
            this.draggedSource = null;
            this.draggedIndex = null;

            const isSourceAvailable = source === 'available';
            const isTargetAvailable = target === 'available';
            const srcSec = isSourceAvailable ? null : parseInt(source, 10);
            const tgtSec = isTargetAvailable ? null : parseInt(target, 10);
            const srcIdx = sourceIndex !== null && sourceIndex !== undefined ? parseInt(sourceIndex, 10) : null;
            const tgtIdx = targetIndex !== null && targetIndex !== undefined ? parseInt(targetIndex, 10) : null;

            if (isTargetAvailable) {
                if (!isSourceAvailable && srcSec !== null && !isNaN(srcSec)) {
                    const srcC = this.selected[srcSec];
                    if (srcC) {
                        if ((srcC.type || 'section') === 'tabs') {
                            (srcC.tabs || []).forEach(t => { t.fields = (t.fields||[]).filter(f => f !== item); });
                        } else {
                            srcC.fields = (srcC.fields||[]).filter(f => f !== item);
                        }
                    }
                    if (!this.available.includes(item)) this.available.push(item);
                    this.updateState();
                }
                return;
            }

            if (tgtSec === null || isNaN(tgtSec)) return;

            if (!this.selected[tgtSec]) {
                this.selected[tgtSec] = {type: 'section', title: 'Section ' + (tgtSec + 1), columns: 2, fields: []};
            }

            const tgtC = this.selected[tgtSec];
            const tgtType = tgtC.type || 'section';

            // Resolve target fields array (tabs → active tab)
            let targetFields;
            if (tgtType === 'tabs') {
                const ti = targetTabIdx !== null ? targetTabIdx : (this.activeTabIndices[tgtSec] ?? 0);
                if (!tgtC.tabs) tgtC.tabs = [];
                if (!tgtC.tabs[ti]) tgtC.tabs[ti] = {label: 'Tab ' + (ti + 1), fields: []};
                targetFields = tgtC.tabs[ti].fields;
            } else {
                if (!Array.isArray(tgtC.fields)) tgtC.fields = [];
                targetFields = tgtC.fields;
            }

            if (isSourceAvailable) {
                this.available = this.available.filter(i => i !== item);
                if (tgtIdx !== null && !isNaN(tgtIdx)) {
                    targetFields.splice(tgtIdx, 0, item);
                } else {
                    targetFields.push(item);
                }
            } else if (srcSec === tgtSec) {
                if (srcIdx !== null && !isNaN(srcIdx) && tgtIdx !== null && !isNaN(tgtIdx) && srcIdx !== tgtIdx) {
                    targetFields.splice(srcIdx, 1);
                    targetFields.splice(tgtIdx, 0, item);
                }
            } else {
                const srcC = this.selected[srcSec];
                if (srcC) {
                    if ((srcC.type || 'section') === 'tabs') {
                        (srcC.tabs || []).forEach(t => { t.fields = (t.fields||[]).filter(f => f !== item); });
                    } else {
                        srcC.fields = (srcC.fields||[]).filter(f => f !== item);
                    }
                }
                if (tgtIdx !== null && !isNaN(tgtIdx)) {
                    targetFields.splice(tgtIdx, 0, item);
                } else {
                    targetFields.push(item);
                }
            }

            this.updateState();
        },

        moveToSection(item, containerIndex, tabIdx = null) {
            if (!this.selected[containerIndex]) {
                this.selected[containerIndex] = {type: 'section', title: 'Section ' + (containerIndex + 1), columns: 2, fields: []};
            }
            if (this.isListView) {
                this.selected[containerIndex].title = 'Fields';
                this.selected[containerIndex].columns = 1;
            }
            const c = this.selected[containerIndex];
            const type = c.type || 'section';
            if (type === 'tabs') {
                const ti = tabIdx !== null ? tabIdx : (this.activeTabIndices[containerIndex] ?? 0);
                if (!c.tabs) c.tabs = [];
                if (!c.tabs[ti]) c.tabs[ti] = {label: 'Tab ' + (ti + 1), fields: []};
                if (!c.tabs[ti].fields.includes(item)) c.tabs[ti].fields.push(item);
            } else {
                if (!Array.isArray(c.fields)) c.fields = [];
                if (!c.fields.includes(item)) c.fields.push(item);
            }
            const idx = this.available.indexOf(item);
            if (idx !== -1) this.available.splice(idx, 1);
            this.updateState();
        },

        moveToAvailable(item, containerIndex) {
            const c = this.selected[containerIndex];
            if (!c) return;
            if ((c.type || 'section') === 'tabs') {
                (c.tabs || []).forEach(t => { t.fields = (t.fields||[]).filter(f => f !== item); });
            } else {
                c.fields = (c.fields||[]).filter(f => f !== item);
            }
            if (!this.available.includes(item)) this.available.push(item);
            this.updateState();
        },

        moveAllToSection(containerIndex) {
            const c = this.selected[containerIndex];
            if (!c) return;
            if ((c.type || 'section') === 'tabs') {
                const ti = this.activeTabIndices[containerIndex] ?? 0;
                if (!c.tabs) c.tabs = [];
                if (!c.tabs[ti]) c.tabs[ti] = {label: 'Tab ' + (ti + 1), fields: []};
                c.tabs[ti].fields = [...c.tabs[ti].fields, ...this.available];
            } else {
                if (!c.fields) c.fields = [];
                c.fields = [...c.fields, ...this.available];
            }
            this.available = [];
            this.updateState();
        },

        moveAllToAvailable() {
            this.selected.forEach(c => {
                this.getAllFieldsFromContainer(c).forEach(f => { if (!this.available.includes(f)) this.available.push(f); });
                if ((c.type || 'section') === 'tabs') {
                    (c.tabs || []).forEach(t => { t.fields = []; });
                } else {
                    c.fields = [];
                }
            });
            this.updateState();
        }
     }">

    <div style="display: grid; grid-template-columns: 1.2fr 1.8fr; gap: 24px; align-items: start;">

        {{-- LEFT COLUMN: Available Fields + Builder Workspace --}}
        <div style="display: flex; flex-direction: column; gap: 16px;">

            {{-- Available Fields pool --}}
            <div style="display:flex; flex-direction:column; height:240px; border:1px solid #e5e7eb; border-radius:12px; background:#fff; overflow:hidden; transition:box-shadow .15s;"
                 :style="isDraggingOverAvailable ? 'box-shadow:0 0 0 2px #f59e0b; border-color:#f59e0b;' : ''"
                 @dragover.prevent="dragOver($event, 'available')"
                 @dragleave="dragLeave"
                 @drop="drop($event, 'available')">

                <div style="padding:10px 14px; background:#f9fafb; border-bottom:1px solid #f3f4f6; display:flex; justify-content:space-between; align-items:center;">
                    <span style="font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:#6b7280;">Available Fields</span>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span style="padding:1px 8px; font-size:11px; font-weight:700; background:#f3f4f6; color:#6b7280; border-radius:9999px;" x-text="available.length"></span>
                        <button type="button" @click="moveAllToAvailable()" style="font-size: 11px; color: #ef4444; font-weight: 500; border: none; background: transparent; cursor: pointer; text-decoration: underline;">Reset All</button>
                    </div>
                </div>

                <div style="padding:8px; border-bottom:1px solid #f3f4f6;">
                    <input type="text"
                           x-model="searchQuery"
                           placeholder="Filter available fields..."
                           style="width:100%; box-sizing:border-box; font-size:12px; padding:5px 10px; border:1px solid #e5e7eb; border-radius:8px; background:#f9fafb; outline:none; color:#374151;" />
                </div>

                <div style="flex:1; overflow-y:auto; padding:8px; display:flex; flex-direction:column; gap:4px;">
                    <template x-for="item in filteredAvailable" :key="item">
                        <div draggable="true"
                             @dragstart="dragStart($event, item, 'available')"
                             @dblclick="moveToSection(item, 0)"
                             style="display:flex; align-items:center; justify-content:space-between; padding:7px 10px; border-radius:8px; background:#f9fafb; border:1px solid #f3f4f6; cursor:grab; transition:background .1s, border-color .1s;"
                             @mouseenter="$el.style.background='#fffbeb'; $el.style.borderColor='#fde68a';"
                             @mouseleave="$el.style.background='#f9fafb'; $el.style.borderColor='#f3f4f6';">
                            <div style="display:flex; align-items:center; gap:8px; min-width:0;">
                                <svg style="width:14px;height:14px;color:#9ca3af;flex-shrink:0;" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                                </svg>
                                <span style="font-size:12px; font-weight:500; color:#374151; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" x-text="fieldLabels[item] || item"></span>
                            </div>
                            <button type="button"
                                    @click.stop="moveToSection(item, 0)"
                                    style="padding:4px; border:none; background:transparent; cursor:pointer; color:#d97706; border-radius:4px;"
                                    title="Add to first container">
                                <svg style="width:13px;height:13px;" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                </svg>
                            </button>
                        </div>
                    </template>

                    <div x-show="filteredAvailable.length === 0"
                         style="flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center; padding:16px; text-align:center;">
                        <span style="font-size:11px; color:#9ca3af;">No fields available</span>
                    </div>
                </div>
            </div>

            {{-- Workspace: Containers --}}
            <div style="display: flex; flex-direction: column; gap: 16px;">
                <div style="display: flex; justify-content: space-between; align-items: center; gap: 8px; flex-wrap: wrap;">
                    <h3 style="font-size: 13px; font-weight: 700; color: #374151; margin: 0;" x-text="isListView ? 'Selected Fields' : 'Layout Containers'"></h3>
                    <div x-show="!isListView" style="display: flex; gap: 6px; flex-shrink: 0;">
                        <button type="button" @click="addSection()"
                                style="display:flex;align-items:center;gap:3px;padding:4px 8px;font-size:11px;font-weight:600;color:#fff;background:#ea580c;border:none;border-radius:6px;cursor:pointer;"
                                @mouseenter="$el.style.background='#c2410c'"
                                @mouseleave="$el.style.background='#ea580c'">
                            <svg style="width:10px;height:10px;" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                            Section
                        </button>
                        <button type="button" @click="addGrid()"
                                style="display:flex;align-items:center;gap:3px;padding:4px 8px;font-size:11px;font-weight:600;color:#fff;background:#0891b2;border:none;border-radius:6px;cursor:pointer;"
                                @mouseenter="$el.style.background='#0e7490'"
                                @mouseleave="$el.style.background='#0891b2'">
                            <svg style="width:10px;height:10px;" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                            Grid
                        </button>
                        <button type="button" @click="addTabs()"
                                style="display:flex;align-items:center;gap:3px;padding:4px 8px;font-size:11px;font-weight:600;color:#fff;background:#7c3aed;border:none;border-radius:6px;cursor:pointer;"
                                @mouseenter="$el.style.background='#6d28d9'"
                                @mouseleave="$el.style.background='#7c3aed'">
                            <svg style="width:10px;height:10px;" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                            Tabs
                        </button>
                    </div>
                </div>

                {{-- Container list --}}
                <template x-for="(sec, secIdx) in selected" :key="secIdx">
                    <div x-show="!isListView"
                         draggable="true"
                         @dragstart="dragStartSection($event, secIdx)"
                         @dragend="dragEnd"
                         @dragover="dragOverSection($event, secIdx)"
                         @drop="dropSection($event, secIdx)"
                         style="border:1px solid #e5e5eb; border-radius:12px; background:#fff; display:flex; flex-direction:column; overflow:hidden; box-shadow:0 1px 2px 0 rgba(0,0,0,0.05); margin-bottom:16px;">

                        {{-- SECTION header --}}
                        <template x-if="(sec.type || 'section') === 'section'">
                            <div style="padding:10px 14px; background:#f8fafc; border-bottom:1px solid #e2e8f0; display:flex; align-items:center; justify-content:space-between; cursor:grab; gap:12px;">
                                <div style="display:flex; align-items:center; gap:8px; flex:1; min-width:0;">
                                    <span style="font-size:9px; font-weight:700; color:#ea580c; text-transform:uppercase; letter-spacing:.06em; flex-shrink:0;">SECTION</span>
                                    <input type="text" x-model="sec.title" @input="updateState()"
                                           style="font-size:12px; font-weight:700; color:#1e293b; background:transparent; border:none; border-bottom:1px dashed transparent; outline:none; padding:2px 4px; width:100%; max-width:180px;"
                                           @focus="$el.style.borderColor='#ea580c'; $el.style.background='#fff';"
                                           @blur="$el.style.borderColor='transparent'; $el.style.background='transparent';" />
                                </div>
                                <div style="display:flex; align-items:center; gap:10px; flex-shrink:0;">
                                    <select x-model.number="sec.columns" @change="updateState()"
                                            style="font-size:11px; padding:2px 6px; border:1px solid #cbd5e1; border-radius:6px; background:#fff; outline:none; color:#475569; height:26px;">
                                        <option value="1">1 Col</option>
                                        <option value="2">2 Cols</option>
                                        <option value="3">3 Cols</option>
                                        <option value="4">4 Cols</option>
                                    </select>
                                    <button type="button" @click="removeSection(secIdx)"
                                            style="border:none; background:transparent; cursor:pointer; color:#ef4444; display:inline-flex; align-items:center; height:26px; width:26px; padding:0;" title="Remove">
                                        <svg style="width:14px;height:14px;" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
                                    </button>
                                </div>
                            </div>
                        </template>

                        {{-- GRID header --}}
                        <template x-if="sec.type === 'grid'">
                            <div style="padding:10px 14px; background:#ecfeff; border-bottom:1px solid #a5f3fc; display:flex; align-items:center; justify-content:space-between; cursor:grab; gap:12px;">
                                <div style="display:flex; align-items:center; gap:8px;">
                                    <span style="font-size:9px; font-weight:700; color:#0891b2; text-transform:uppercase; letter-spacing:.06em; flex-shrink:0;">GRID</span>
                                    <svg style="width:13px;height:13px;color:#0891b2;" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z"/></svg>
                                    <span style="font-size:11px; color:#0e7490;">No heading — fields flow in a flat grid</span>
                                </div>
                                <div style="display:flex; align-items:center; gap:10px; flex-shrink:0;">
                                    <select x-model.number="sec.columns" @change="updateState()"
                                            style="font-size:11px; padding:2px 6px; border:1px solid #a5f3fc; border-radius:6px; background:#fff; outline:none; color:#0e7490; height:26px;">
                                        <option value="1">1 Col</option>
                                        <option value="2">2 Cols</option>
                                        <option value="3">3 Cols</option>
                                        <option value="4">4 Cols</option>
                                    </select>
                                    <button type="button" @click="removeSection(secIdx)"
                                            style="border:none; background:transparent; cursor:pointer; color:#ef4444; display:inline-flex; align-items:center; height:26px; width:26px; padding:0;" title="Remove">
                                        <svg style="width:14px;height:14px;" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
                                    </button>
                                </div>
                            </div>
                        </template>

                        {{-- TABS header --}}
                        <template x-if="sec.type === 'tabs'">
                            <div>
                                <div style="padding:8px 14px; background:#f5f3ff; border-bottom:1px solid #ddd6fe; display:flex; align-items:center; justify-content:space-between; cursor:grab; gap:8px;">
                                    <div style="display:flex; align-items:center; gap:8px; flex:1; min-width:0;">
                                        <span style="font-size:9px; font-weight:700; color:#7c3aed; text-transform:uppercase; letter-spacing:.06em; flex-shrink:0;">TABS</span>
                                        <input type="text" x-model="sec.title" @input="updateState()" placeholder="Optional heading..."
                                               style="font-size:11px; color:#5b21b6; background:transparent; border:none; border-bottom:1px dashed transparent; outline:none; padding:2px 4px; width:100%; max-width:160px;"
                                               @focus="$el.style.borderColor='#7c3aed'; $el.style.background='#fff';"
                                               @blur="$el.style.borderColor='transparent'; $el.style.background='transparent';" />
                                    </div>
                                    <div style="display:flex; align-items:center; gap:8px; flex-shrink:0;">
                                        <select x-model.number="sec.columns" @change="updateState()"
                                                style="font-size:11px; padding:2px 6px; border:1px solid #c4b5fd; border-radius:6px; background:#fff; outline:none; color:#5b21b6; height:26px;">
                                            <option value="1">1 Col</option>
                                            <option value="2">2 Cols</option>
                                            <option value="3">3 Cols</option>
                                            <option value="4">4 Cols</option>
                                        </select>
                                    <button type="button" @click="removeSection(secIdx)"
                                            style="border:none; background:transparent; cursor:pointer; color:#ef4444; display:inline-flex; align-items:center; height:26px; width:26px; padding:0;" title="Remove tabs container">
                                        <svg style="width:14px;height:14px;" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
                                    </button>
                                    </div>
                                </div>
                                {{-- Tab strip --}}
                                <div style="display:flex; align-items:stretch; background:#faf5ff; border-bottom:1px solid #ddd6fe; padding:0 8px; overflow-x:auto;">
                                    <template x-for="(tab, tabIdx) in (sec.tabs || [])" :key="tabIdx">
                                        <div style="display:flex; align-items:center; gap:4px; padding:6px 10px; cursor:pointer; border-bottom:2px solid transparent; transition:border-color .15s;"
                                             :style="getActiveTabIdx(secIdx) === tabIdx ? 'border-bottom-color:#7c3aed; background:#fff;' : ''"
                                             @click="setActiveTab(secIdx, tabIdx)">
                                            <input type="text" x-model="tab.label" @input="updateState()" @click.stop=""
                                                   style="font-size:11px; font-weight:600; background:transparent; border:none; outline:none; width:60px; min-width:40px; max-width:100px;"
                                                   :style="getActiveTabIdx(secIdx) === tabIdx ? 'color:#7c3aed;' : 'color:#9ca3af;'" />
                                            <button type="button" @click.stop="removeTab(secIdx, tabIdx)"
                                                    x-show="(sec.tabs || []).length > 1"
                                                    style="border:none; background:transparent; cursor:pointer; color:#d1d5db; padding:0; display:inline-flex; align-items:center;"
                                                    @mouseenter="$el.style.color='#ef4444'"
                                                    @mouseleave="$el.style.color='#d1d5db'">
                                                <svg style="width:10px;height:10px;" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                            </button>
                                        </div>
                                    </template>
                                    <button type="button" @click="addTab(secIdx)"
                                            style="padding:4px 10px; border:none; background:transparent; cursor:pointer; color:#7c3aed; font-size:11px; font-weight:600; white-space:nowrap; align-self:center;">+ Tab</button>
                                </div>
                            </div>
                        </template>

                        {{-- Drop zone: Section / Grid --}}
                        <template x-if="(sec.type || 'section') !== 'tabs'">
                            <div @dragover.prevent=""
                                 @drop.stop="drop($event, secIdx)"
                                 style="min-height:80px; padding:8px; display:flex; flex-direction:column; gap:6px; background:#fafafa;">

                                <template x-for="(item, idx) in (sec.fields || [])" :key="item">
                                    <div draggable="true" @dragend="dragEnd"
                                         @dragstart.stop="dragStart($event, item, secIdx, idx)"
                                         @dragover.prevent=""
                                         @drop.stop="drop($event, secIdx, idx)"
                                         @dblclick="moveToAvailable(item, secIdx)"
                                         style="display:flex; align-items:center; justify-content:space-between; padding:6px 10px; border-radius:8px; background:#fffbeb; border:1px solid #fde68a; cursor:grab;"
                                         @mouseenter="$el.style.background='#fef3c7';"
                                         @mouseleave="$el.style.background='#fffbeb';">
                                        <div style="display:flex; align-items:center; gap:8px; min-width:0;">
                                            <svg style="width:14px;height:14px;color:#d97706;flex-shrink:0;" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/></svg>
                                            <span style="font-size:11px; font-weight:600; color:#451a03; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" x-text="fieldLabels[item] || item"></span>
                                        </div>
                                        <button type="button" @click="moveToAvailable(item, secIdx)"
                                                style="padding:2px; border:none; background:transparent; cursor:pointer; color:#ef4444;">
                                            <svg style="width:13px;height:13px;" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 12h-15"/></svg>
                                        </button>
                                    </div>
                                </template>

                                <div x-show="!sec.fields || sec.fields.length === 0"
                                     style="flex:1; display:flex; align-items:center; justify-content:center; border:2px dashed #cbd5e1; border-radius:8px; padding:12px; text-align:center; color:#94a3b8; font-size:11px;">
                                    Drag available fields here
                                </div>
                            </div>
                        </template>

                        {{-- Drop zone: Tabs (active tab) --}}
                        <template x-if="sec.type === 'tabs'">
                            <div @dragover.prevent=""
                                 @drop.stop="drop($event, secIdx, null, getActiveTabIdx(secIdx))"
                                 style="min-height:80px; padding:8px; display:flex; flex-direction:column; gap:6px; background:#faf5ff;">

                                <template x-for="(item, idx) in ((sec.tabs || [])[getActiveTabIdx(secIdx)]?.fields || [])" :key="item">
                                    <div draggable="true" @dragend="dragEnd"
                                         @dragstart.stop="dragStart($event, item, secIdx, idx)"
                                         @dragover.prevent=""
                                         @drop.stop="drop($event, secIdx, idx, getActiveTabIdx(secIdx))"
                                         @dblclick="moveToAvailable(item, secIdx)"
                                         style="display:flex; align-items:center; justify-content:space-between; padding:6px 10px; border-radius:8px; background:#ede9fe; border:1px solid #ddd6fe; cursor:grab;"
                                         @mouseenter="$el.style.background='#ddd6fe';"
                                         @mouseleave="$el.style.background='#ede9fe';">
                                        <div style="display:flex; align-items:center; gap:8px; min-width:0;">
                                            <svg style="width:14px;height:14px;color:#7c3aed;flex-shrink:0;" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/></svg>
                                            <span style="font-size:11px; font-weight:600; color:#2e1065; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" x-text="fieldLabels[item] || item"></span>
                                        </div>
                                        <button type="button" @click="moveToAvailable(item, secIdx)"
                                                style="padding:2px; border:none; background:transparent; cursor:pointer; color:#ef4444;">
                                            <svg style="width:13px;height:13px;" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 12h-15"/></svg>
                                        </button>
                                    </div>
                                </template>

                                <div x-show="!((sec.tabs || [])[getActiveTabIdx(secIdx)]?.fields?.length)"
                                     style="flex:1; display:flex; align-items:center; justify-content:center; border:2px dashed #c4b5fd; border-radius:8px; padding:12px; text-align:center; color:#7c3aed; font-size:11px;">
                                    Drag fields here — they go into the active tab
                                </div>
                            </div>
                        </template>

                    </div>
                </template>

                {{-- Flat List View Dropzone --}}
                <div x-show="isListView"
                     @dragover.prevent=""
                     @drop.stop="drop($event, 0)"
                     style="min-height:220px; padding:12px; display:flex; flex-direction:column; gap:6px; background:#fff; border:1px solid #e5e7eb; border-radius:12px; box-sizing:border-box;">

                    <template x-for="(item, idx) in (selected[0] ? selected[0].fields : [])" :key="item">
                        <div draggable="true"
                             @dragstart.stop="dragStart($event, item, 0, idx)"
                             @dragover.prevent=""
                             @drop.stop="drop($event, 0, idx)"
                             @dblclick="moveToAvailable(item, 0)"
                             style="display:flex; align-items:center; justify-content:space-between; padding:6px 10px; border-radius:8px; background:#fffbeb; border:1px solid #fde68a; cursor:grab;"
                             @mouseenter="$el.style.background='#fef3c7';"
                             @mouseleave="$el.style.background='#fffbeb';">
                            <div style="display:flex; align-items:center; gap:8px; min-width:0;">
                                <svg style="width:14px;height:14px;color:#d97706;flex-shrink:0;" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/></svg>
                                <span style="font-size:11px; font-weight:600; color:#451a03; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" x-text="fieldLabels[item] || item"></span>
                            </div>
                            <button type="button" @click="moveToAvailable(item, 0)"
                                    style="padding:2px; border:none; background:transparent; cursor:pointer; color:#ef4444;">
                                <svg style="width:13px;height:13px;" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 12h-15"/></svg>
                            </button>
                        </div>
                    </template>

                    <div x-show="!selected[0] || !selected[0].fields || selected[0].fields.length === 0"
                         style="flex:1; display:flex; align-items:center; justify-content:center; border:2px dashed #cbd5e1; border-radius:8px; padding:12px; text-align:center; color:#94a3b8; font-size:11px;">
                        Drag available fields here
                    </div>
                </div>
            </div>
        </div>

        {{-- RIGHT COLUMN: Real-Time Preview --}}
        <div style="position:sticky; top:16px; border:1px solid #cbd5e1; border-radius:16px; background:#f8fafc; overflow:hidden; box-shadow:0 4px 6px -1px rgba(0,0,0,0.05);">

            <div style="padding:12px 16px; background:#1e293b; border-bottom:1px solid #cbd5e1; display:flex; justify-content:space-between; align-items:center; color:#fff;">
                <div style="display:flex; align-items:center; gap:8px;">
                    <span style="display:inline-block; width:8px; height:8px; border-radius:9999px; background:#22c55e;"></span>
                    <span style="font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.05em;" x-text="isListView ? 'Real-Time Table Columns' : 'Real-Time Form Preview'"></span>
                </div>
                <span style="font-size:11px; color:#94a3b8;" x-text="isListView ? 'Table Columns Mockup' : 'Client Mockup'"></span>
            </div>

            <div style="padding:20px; min-height:480px; max-height:580px; overflow-y:auto; display:flex; flex-direction:column; gap:20px;">

                {{-- Empty state --}}
                <div x-show="selected.length === 0 || selected.every(c => getAllFieldsFromContainer(c).length === 0)"
                     style="display:flex; flex-direction:column; align-items:center; justify-content:center; gap:8px; min-height:360px; text-align:center; color:#64748b;">
                    <svg style="width:48px;height:48px;color:#cbd5e1;" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6a7.5 7.5 0 107.5 7.5h-7.5V6z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5H21A7.5 7.5 0 0013.5 3v7.5z"/>
                    </svg>
                    <h4 style="font-weight:700; font-size:13px; margin:0;">Preview Empty</h4>
                    <p style="font-size:11px; margin:0; max-width:200px;">Drag fields from available panel to construct your live mockup.</p>
                </div>

                {{-- List view table preview --}}
                <template x-if="isListView">
                    <div x-show="selected.length > 0 && selected[0].fields && selected[0].fields.length > 0"
                         style="border:1px solid #e2e8f0; border-radius:12px; background:#fff; overflow:hidden;">
                        <table style="width:100%; border-collapse:collapse; text-align:left; font-size:11px;">
                            <thead>
                                <tr style="background:#f8fafc; border-bottom:1px solid #e2e8f0;">
                                    <template x-for="field in (selected[0] ? selected[0].fields : [])" :key="field">
                                        <th style="padding:10px 14px; font-weight:600; color:#475569;" x-text="fieldLabels[field] || field.replace(/_/g,' ').replace(/\b\w/g,c=>c.toUpperCase())"></th>
                                    </template>
                                </tr>
                            </thead>
                            <tbody>
                                <tr style="border-bottom:1px solid #f1f5f9;">
                                    <template x-for="field in (selected[0] ? selected[0].fields : [])" :key="field">
                                        <td style="padding:12px 14px; color:#94a3b8; font-style:italic;">Data...</td>
                                    </template>
                                </tr>
                                <tr>
                                    <template x-for="field in (selected[0] ? selected[0].fields : [])" :key="field">
                                        <td style="padding:12px 14px; color:#94a3b8; font-style:italic;">Data...</td>
                                    </template>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </template>

                {{-- Form preview --}}
                <template x-if="!isListView">
                    <div style="display:flex; flex-direction:column; gap:20px;">
                        <template x-for="(container, cIdx) in selected" :key="cIdx">
                            <div x-show="getAllFieldsFromContainer(container).length > 0">

                                {{-- Section preview --}}
                                <template x-if="(container.type || 'section') === 'section'">
                                    <div style="border:1px solid #e2e8f0; border-radius:12px; background:#fff; padding:16px; box-shadow:0 1px 3px 0 rgba(0,0,0,0.02); display:flex; flex-direction:column; gap:14px;">
                                        <div style="border-bottom:1px solid #f1f5f9; padding-bottom:8px;">
                                            <h4 style="font-size:12px; font-weight:700; color:#0f172a; margin:0;" x-text="container.title || 'Untitled Section'"></h4>
                                        </div>
                                        <div style="display:grid; gap:16px;" :style="{ gridTemplateColumns: `repeat(${container.columns || 2}, minmax(0, 1fr))` }">
                                            <template x-for="field in (container.fields || [])" :key="field">
                                                <div style="display:flex; flex-direction:column; gap:4px; min-width:0;">
                                                    <label style="font-size:11px; font-weight:600; color:#475569;">
                                                        <span x-text="fieldLabels[field] || field.replace(/_/g,' ').replace(/\b\w/g,c=>c.toUpperCase())"></span>
                                                    </label>
                                                    <div style="border:1px solid #cbd5e1; border-radius:8px; height:32px; padding:6px 10px; background:#fff; font-size:11px; color:#cbd5e1; display:flex; align-items:center; box-sizing:border-box;">...</div>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </template>

                                {{-- Grid preview --}}
                                <template x-if="container.type === 'grid'">
                                    <div style="border:1px solid #a5f3fc; border-radius:12px; background:#fff; padding:16px; box-shadow:0 1px 3px 0 rgba(0,0,0,0.02);">
                                        <div style="display:flex; align-items:center; gap:6px; border-bottom:1px solid #e0f2fe; padding-bottom:8px; margin-bottom:14px;">
                                            <svg style="width:12px;height:12px;color:#0891b2;" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z"/></svg>
                                            <span style="font-size:10px; font-weight:700; color:#0891b2; text-transform:uppercase; letter-spacing:.05em;" x-text="'Grid — ' + (container.columns || 2) + ' cols'"></span>
                                        </div>
                                        <div style="display:grid; gap:16px;" :style="{ gridTemplateColumns: `repeat(${container.columns || 2}, minmax(0, 1fr))` }">
                                            <template x-for="field in (container.fields || [])" :key="field">
                                                <div style="display:flex; flex-direction:column; gap:4px; min-width:0;">
                                                    <label style="font-size:11px; font-weight:600; color:#0e7490;">
                                                        <span x-text="fieldLabels[field] || field.replace(/_/g,' ').replace(/\b\w/g,c=>c.toUpperCase())"></span>
                                                    </label>
                                                    <div style="border:1px solid #a5f3fc; border-radius:8px; height:32px; padding:6px 10px; background:#f0fdff; font-size:11px; color:#94a3b8; display:flex; align-items:center; box-sizing:border-box;">...</div>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </template>

                                {{-- Tabs preview --}}
                                <template x-if="container.type === 'tabs'">
                                    <div style="border:1px solid #ddd6fe; border-radius:12px; background:#fff; overflow:hidden; box-shadow:0 1px 3px 0 rgba(0,0,0,0.02);">
                                        <template x-if="container.title">
                                            <div style="padding:8px 14px; border-bottom:1px solid #f1f5f9;">
                                                <span style="font-size:12px; font-weight:700; color:#0f172a;" x-text="container.title"></span>
                                            </div>
                                        </template>
                                        <div style="display:flex; background:#faf5ff; border-bottom:1px solid #ddd6fe;">
                                            <template x-for="(tab, ti) in (container.tabs || [])" :key="ti">
                                                <span style="padding:8px 14px; font-size:11px; font-weight:600; cursor:pointer; border-bottom:2px solid transparent;"
                                                      :style="ti === 0 ? 'border-bottom-color:#7c3aed; color:#7c3aed;' : 'color:#9ca3af;'"
                                                      x-text="tab.label || 'Tab'"></span>
                                            </template>
                                        </div>
                                        <div style="padding:16px;">
                                            <template x-if="(container.tabs || []).length > 0 && ((container.tabs || [])[0]?.fields || []).length > 0">
                                                <div style="display:grid; gap:12px;" :style="{ gridTemplateColumns: `repeat(${container.columns || 1}, minmax(0, 1fr))` }">
                                                    <template x-for="field in ((container.tabs || [])[0]?.fields || [])" :key="field">
                                                        <div style="display:flex; flex-direction:column; gap:4px; min-width:0;">
                                                            <label style="font-size:11px; font-weight:600; color:#475569;">
                                                                <span x-text="fieldLabels[field] || field.replace(/_/g,' ').replace(/\b\w/g,c=>c.toUpperCase())"></span>
                                                            </label>
                                                            <div style="border:1px solid #ddd6fe; border-radius:8px; height:32px; padding:6px 10px; background:#fdf4ff; font-size:11px; color:#94a3b8; display:flex; align-items:center; box-sizing:border-box;">...</div>
                                                        </div>
                                                    </template>
                                                </div>
                                            </template>
                                            <template x-if="(container.tabs || []).length === 0 || ((container.tabs || [])[0]?.fields || []).length === 0">
                                                <p style="font-size:11px; color:#9ca3af; margin:0;">No fields in first tab yet.</p>
                                            </template>
                                        </div>
                                    </div>
                                </template>

                            </div>
                        </template>
                    </div>
                </template>
            </div>
        </div>
    </div>
</div>
