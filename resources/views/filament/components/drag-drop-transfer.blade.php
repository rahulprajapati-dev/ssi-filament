<div class="fi-fo-drag-drop-transfer"
     x-data="{
        available: [],
        selected: @entangle($getStatePath()) ?? [],
        searchQuery: '',
        isDraggingOverAvailable: false,
        draggedItem: null,
        draggedSource: null, // 'available' or section index
        draggedIndex: null,  // index within source
        source: '{{ $source ?? 'repeater' }}',
        dependsOn: '{{ $dependsOn ?? '' }}',
        draggedSectionIndex: null, // for reordering sections
        isDraggingSection: false,

        isListView: false,

        init() {
            this.normalizeSelected();

            const currentType = this.$wire.data?.layout_type;
            this.isListView = (currentType === 'list');

            this.$nextTick(() => {
                const innerType = this.$wire.data?.layout_type;
                this.isListView = (innerType === 'list');
            });

            this.$watch('$wire.data.layout_type', (newType) => {
                this.isListView = (newType === 'list');
            });

            if (this.source === 'module_fields') {
                this.$nextTick(async () => {
                    const mid = this.$wire.data?.[this.dependsOn];
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
            if (!this.selected) {
                this.selected = [];
            }
            // If it is stored as flat legacy array of field strings, convert to section format
            if (Array.isArray(this.selected)) {
                if (this.selected.length > 0 && typeof this.selected[0] === 'string') {
                    this.selected = [{
                        title: 'Default Section',
                        columns: '2',
                        fields: [...this.selected]
                    }];
                }
            } else {
                // Object or empty
                this.selected = [];
            }

            if (this.selected.length === 0) {
                this.addSection();
            }
        },

        async syncFromModuleId(moduleId) {
            if (!moduleId) {
                this.available = [];
                return;
            }
            try {
                const allFields = await this.$wire.call('getModuleFields', parseInt(moduleId));
                
                // Collect all currently selected fields in all sections
                const selectedFieldsSet = new Set();
                this.selected.forEach(sec => {
                    if (Array.isArray(sec.fields)) {
                        sec.fields.forEach(f => selectedFieldsSet.add(f));
                    }
                });

                this.available = (allFields || []).filter(f => !selectedFieldsSet.has(f));
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

            const selectedFieldsSet = new Set();
            this.selected.forEach(sec => {
                if (Array.isArray(sec.fields)) {
                    sec.fields.forEach(f => selectedFieldsSet.add(f));
                }
            });

            this.available = allFields.filter(field => !selectedFieldsSet.has(field));
        },

        get filteredAvailable() {
            if (!this.searchQuery) return this.available;
            const query = this.searchQuery.toLowerCase();
            return this.available.filter(item => item.toLowerCase().includes(query));
        },

        addSection() {
            this.selected.push({
                title: 'Section ' + (this.selected.length + 1),
                columns: '2',
                fields: []
            });
            this.updateState();
        },

        removeSection(index) {
            if (this.selected.length <= 1) {
                // Keep at least one section, move remaining fields to available
                const fields = this.selected[index].fields || [];
                fields.forEach(f => {
                    if (!this.available.includes(f)) this.available.push(f);
                });
                this.selected[index].fields = [];
                this.selected[index].title = 'Default Section';
                this.selected[index].columns = '2';
                this.updateState();
                return;
            }
            // Put fields back to available pool
            const fields = this.selected[index].fields || [];
            fields.forEach(f => {
                if (!this.available.includes(f)) this.available.push(f);
            });
            this.selected.splice(index, 1);
            this.updateState();
        },

        updateState() {
            // Force Alpine and Livewire sync
            this.selected = [...this.selected];
        },

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

        dragOverSection(event, index) {
            event.preventDefault();
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
            if (target === 'available') {
                this.isDraggingOverAvailable = true;
            }
        },

        dragLeave() {
            this.isDraggingOverAvailable = false;
        },

        drop(event, target, targetIndex = null) {
            event.preventDefault();
            this.isDraggingOverAvailable = false;

            if (this.isDraggingSection) return;
            if (!this.draggedItem) return;

            const item = this.draggedItem;
            const source = this.draggedSource;
            const sourceIndex = this.draggedIndex;

            this.draggedItem = null;
            this.draggedSource = null;
            this.draggedIndex = null;

            // Dropping back into available pool
            if (target === 'available') {
                if (source !== 'available') {
                    // Remove from original section
                    this.selected[source].fields = this.selected[source].fields.filter(f => f !== item);
                    if (!this.available.includes(item)) {
                        this.available.push(item);
                    }
                    this.updateState();
                }
                return;
            }

            // Ensure target section exists (especially for list view where selected[0] may be missing)
            if (typeof target === 'number') {
                if (!this.selected[target]) {
                    this.selected[target] = { title: 'Section ' + (target + 1), columns: '2', fields: [] };
                } else if (!Array.isArray(this.selected[target].fields)) {
                    this.selected[target].fields = [];
                }
            }
            const targetSection = this.selected[target];
            if (!targetSection.fields) targetSection.fields = [];

            if (source === 'available') {
                // Move from available to section
                this.available = this.available.filter(i => i !== item);
                if (targetIndex !== null) {
                    targetSection.fields.splice(targetIndex, 0, item);
                } else {
                    targetSection.fields.push(item);
                }
            } else if (source === target) {
                // Reorder within the same section
                if (sourceIndex !== null && targetIndex !== null && sourceIndex !== targetIndex) {
                    targetSection.fields.splice(sourceIndex, 1);
                    targetSection.fields.splice(targetIndex, 0, item);
                }
            } else {
                // Move from one section to another
                if (Array.isArray(this.selected[source].fields)) {
                    this.selected[source].fields = this.selected[source].fields.filter(f => f !== item);
                }
                if (targetIndex !== null) {
                    targetSection.fields.splice(targetIndex, 0, item);
                } else {
                    targetSection.fields.push(item);
                }
            }

            this.updateState();
        },

        moveToSection(item, sectionIndex) {
            this.available = this.available.filter(i => i !== item);
            const sec = this.selected[sectionIndex];
            if (!sec.fields) sec.fields = [];
            if (!sec.fields.includes(item)) {
                sec.fields.push(item);
            }
            this.updateState();
        },

        moveToAvailable(item, sectionIndex) {
            this.selected[sectionIndex].fields = this.selected[sectionIndex].fields.filter(f => f !== item);
            if (!this.available.includes(item)) {
                this.available.push(item);
            }
            this.updateState();
        },

        moveAllToSection(sectionIndex) {
            const sec = this.selected[sectionIndex];
            if (!sec.fields) sec.fields = [];
            sec.fields = [...sec.fields, ...this.available];
            this.available = [];
            this.updateState();
        },

        moveAllToAvailable() {
            this.selected.forEach(sec => {
                const fields = sec.fields || [];
                fields.forEach(f => {
                    if (!this.available.includes(f)) this.available.push(f);
                });
                sec.fields = [];
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
                 @dragover="dragOver($event, 'available')"
                 @dragleave="dragLeave"
                 @drop="drop($event, 'available')">

                {{-- Header --}}
                <div style="padding:10px 14px; background:#f9fafb; border-bottom:1px solid #f3f4f6; display:flex; justify-content:space-between; align-items:center;">
                    <span style="font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:#6b7280;">Available Fields</span>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span style="padding:1px 8px; font-size:11px; font-weight:700; background:#f3f4f6; color:#6b7280; border-radius:9999px;" x-text="available.length"></span>
                        <button type="button" @click="moveAllToAvailable()" style="font-size: 11px; color: #ef4444; font-weight: 500; border: none; background: transparent; cursor: pointer; text-decoration: underline;">Reset All</button>
                    </div>
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
                             @dblclick="moveToSection(item, 0)"
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
                                    @click="moveToSection(item, 0)"
                                    style="padding:4px; border:none; background:transparent; cursor:pointer; color:#d97706; border-radius:4px;"
                                    title="Add to Section 1">
                                <svg style="width:13px;height:13px;" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                </svg>
                            </button>
                        </div>
                    </template>

                    {{-- Empty state --}}
                    <div x-show="filteredAvailable.length === 0"
                         style="flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center; padding:16px; text-align:center;">
                        <span style="font-size:11px; color:#9ca3af;">No fields available</span>
                    </div>
                </div>
            </div>

            {{-- Workspace with Sections --}}
            <div style="display: flex; flex-direction: column; gap: 16px;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <h3 style="font-size: 13px; font-weight: 700; color: #374151;" x-text="isListView ? 'Selected Fields' : 'Sections & Groups'"></h3>
                    <button x-show="!isListView" type="button" @click="addSection()"
                            style="display: flex; align-items: center; gap: 4px; padding: 4px 10px; font-size: 12px; font-weight: 600; color: #fff; background: #ea580c; border: none; border-radius: 6px; cursor: pointer; transition: background .15s;"
                            @mouseenter="$el.style.background='#c2410c'"
                            @mouseleave="$el.style.background='#ea580c'">

                        Add Section
                    </button>
                </div>

                <template x-for="(sec, secIdx) in selected" :key="secIdx">
                    <div x-show="!isListView"
                         draggable="true"
                         @dragstart="dragStartSection($event, secIdx)"
                         @dragover="dragOverSection($event, secIdx)"
                         @drop="dropSection($event, secIdx)"
                         style="border: 1px solid #e5e7eb; border-radius: 12px; background: #fff; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 1px 2px 0 rgba(0,0,0,0.05); margin-bottom: 16px;">
                        
                        {{-- Section Header --}}
                        <div style="padding: 10px 14px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; display: flex; flex-direction: row; align-items: center; justify-content: space-between; cursor: grab; gap: 12px;">
                            <div style="display: flex; flex-direction: row; align-items: center; gap: 8px; flex: 1; min-width: 0;">
                                <svg style="width:14px;height:14px;color:#94a3b8;flex-shrink:0;" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 9h16.5m-16.5 6.75h16.5" />
                                </svg>
                                <input type="text" x-model="sec.title" @input="updateState()"
                                       style="font-size: 12px; font-weight: 700; color: #1e293b; background: transparent; border: none; border-bottom: 1px dashed transparent; outline: none; padding: 2px 4px; width: 100%; max-width: 180px; transition: border-color .15s;"
                                       @focus="$el.style.borderColor='#ea580c'; $el.style.background='#fff';"
                                       @blur="$el.style.borderColor='transparent'; $el.style.background='transparent';" />
                            </div>
                            <div style="display: flex; flex-direction: row; align-items: center; gap: 10px; flex-shrink: 0;">
                                <select x-model="sec.columns" @change="updateState()"
                                        style="font-size: 11px; padding: 2px 6px; border: 1px solid #cbd5e1; border-radius: 6px; background: #fff; outline: none; color: #475569; height: 26px;">
                                    <option value="1">1 Col</option>
                                    <option value="2">2 Cols</option>
                                    <option value="3">3 Cols</option>
                                    <option value="4">4 Cols</option>
                                </select>
                                <button type="button" @click="removeSection(secIdx)" style="border: none; background: transparent; cursor: pointer; color: #ef4444; display: inline-flex; align-items: center; justify-content: center; height: 26px; width: 26px; padding: 0;" title="Delete Section">
                                    <svg style="width: 14px; height: 14px;" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        {{-- Section Fields list (Dropzone) --}}
                        <div @dragover.prevent=""
                             @drop.stop="drop($event, secIdx)"
                             style="min-height: 80px; padding: 8px; display: flex; flex-direction: column; gap: 6px; background: #fafafa;">
                            
                            <template x-for="(item, idx) in sec.fields" :key="item">
                                <div draggable="true"
                                     @dragstart="dragStart($event, item, secIdx, idx)"
                                     @dragover.prevent=""
                                     @drop.stop="drop($event, secIdx, idx)"
                                     @dblclick="moveToAvailable(item, secIdx)"
                                     style="display: flex; align-items: center; justify-content: space-between; padding: 6px 10px; border-radius: 8px; background: #fffbeb; border: 1px solid #fde68a; cursor: grab; transition: background .1s;"
                                     @mouseenter="$el.style.background='#fef3c7';"
                                     @mouseleave="$el.style.background='#fffbeb';">
                                    <div style="display: flex; align-items: center; gap: 8px; min-width: 0;">
                                        <svg style="width: 14px; height: 14px; color: #d97706; flex-shrink: 0;" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                                        </svg>
                                        <span style="font-size: 11px; font-weight: 600; color: #451a03; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" x-text="item"></span>
                                    </div>
                                    <button type="button" @click="moveToAvailable(item, secIdx)"
                                            style="padding: 2px; border: none; background: transparent; cursor: pointer; color: #ef4444;">
                                        <svg style="width: 13px; height: 13px;" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 12h-15" />
                                        </svg>
                                    </button>
                                </div>
                            </template>

                            <div x-show="!sec.fields || sec.fields.length === 0"
                                 style="flex: 1; display: flex; align-items: center; justify-content: center; border: 2px dashed #cbd5e1; border-radius: 8px; padding: 12px; text-align: center; color: #94a3b8; font-size: 11px;">
                                Drag available fields here
                            </div>
                        </div>

                    </div>
                </template>

                {{-- Dedicated Flat List View Dropzone --}}
                <div x-show="isListView"
                     @dragover.prevent=""
                     @drop.stop="drop($event, 0)"
                     style="min-height: 220px; padding: 12px; display: flex; flex-direction: column; gap: 6px; background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; box-sizing: border-box;">
                    
                    <template x-for="(item, idx) in (selected[0] ? selected[0].fields : [])" :key="item">
                        <div draggable="true"
                             @dragstart="dragStart($event, item, 0, idx)"
                             @dragover.prevent=""
                             @drop.stop="drop($event, 0, idx)"
                             @dblclick="moveToAvailable(item, 0)"
                             style="display: flex; align-items: center; justify-content: space-between; padding: 6px 10px; border-radius: 8px; background: #fffbeb; border: 1px solid #fde68a; cursor: grab; transition: background .1s;"
                             @mouseenter="$el.style.background='#fef3c7';"
                             @mouseleave="$el.style.background='#fffbeb';">
                            <div style="display: flex; align-items: center; gap: 8px; min-width: 0;">
                                <svg style="width: 14px; height: 14px; color: #d97706; flex-shrink: 0;" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                                </svg>
                                <span style="font-size: 11px; font-weight: 600; color: #451a03; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" x-text="item"></span>
                            </div>
                            <button type="button" @click="moveToAvailable(item, 0)"
                                    style="padding: 2px; border: none; background: transparent; cursor: pointer; color: #ef4444;">
                                <svg style="width: 13px; height: 13px;" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 12h-15" />
                                </svg>
                            </button>
                        </div>
                    </template>

                    <div x-show="!selected[0] || !selected[0].fields || selected[0].fields.length === 0"
                         style="flex: 1; display: flex; align-items: center; justify-content: center; border: 2px dashed #cbd5e1; border-radius: 8px; padding: 12px; text-align: center; color: #94a3b8; font-size: 11px;">
                        Drag available fields here
                    </div>
                </div>
            </div>
            
        </div>

        {{-- RIGHT COLUMN: Real-Time Live Preview Mockup Form --}}
        <div style="position: sticky; top: 16px; border: 1px solid #cbd5e1; border-radius: 16px; background: #f8fafc; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);">
            
            {{-- Preview Header --}}
            <div style="padding: 12px 16px; background: #1e293b; border-bottom: 1px solid #cbd5e1; display: flex; justify-content: space-between; align-items: center; color: #fff;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span style="display: inline-block; width: 8px; height: 8px; border-radius: 9999px; background: #22c55e;"></span>
                    <span style="font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em;" x-text="isListView ? 'Real-Time Table Columns' : 'Real-Time Form Preview'"></span>
                </div>
                <span style="font-size: 11px; color: #94a3b8;" x-text="isListView ? 'Table Columns Mockup' : 'Client Mockup'"></span>
            </div>

            {{-- Mockup Screen --}}
            <div style="padding: 20px; min-height: 480px; max-height: 580px; overflow-y: auto; display: flex; flex-direction: column; gap: 20px;">
                
                {{-- No layout alert --}}
                <div x-show="selected.length === 0 || (selected.length === 1 && (!selected[0].fields || selected[0].fields.length === 0))"
                     style="display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px; min-height: 360px; text-align: center; color: #64748b;">
                     <svg style="width: 48px; height: 48px; color: #cbd5e1;" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                         <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6a7.5 7.5 0 107.5 7.5h-7.5V6z" />
                         <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5H21A7.5 7.5 0 0013.5 3v7.5z" />
                     </svg>
                     <h4 style="font-weight: 700; font-size: 13px; margin: 0;">Preview Empty</h4>
                     <p style="font-size: 11px; margin: 0; max-width: 200px;">Drag fields from available panel to construct your live mockup.</p>
                 </div>

                 {{-- Render List View Table Columns Preview --}}
                 <template x-if="isListView">
                     <div x-show="selected.length > 0 && selected[0].fields && selected[0].fields.length > 0"
                          style="border: 1px solid #e2e8f0; border-radius: 12px; background: #fff; overflow: hidden; box-shadow: 0 1px 3px 0 rgba(0,0,0,0.02);">
                         <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 11px;">
                             <thead>
                                 <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                                     <template x-for="field in (selected[0] ? selected[0].fields : [])" :key="field">
                                         <th style="padding: 10px 14px; font-weight: 600; color: #475569;" x-text="field.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())"></th>
                                     </template>
                                 </tr>
                             </thead>
                             <tbody>
                                 <tr style="border-bottom: 1px solid #f1f5f9;">
                                     <template x-for="field in (selected[0] ? selected[0].fields : [])" :key="field">
                                         <td style="padding: 12px 14px; color: #94a3b8; font-style: italic;">Data...</td>
                                     </template>
                                 </tr>
                                 <tr>
                                     <template x-for="field in (selected[0] ? selected[0].fields : [])" :key="field">
                                         <td style="padding: 12px 14px; color: #94a3b8; font-style: italic;">Data...</td>
                                     </template>
                                 </tr>
                             </tbody>
                         </table>
                     </div>
                 </template>
 
                {{-- Render Form Sections --}}
                <template x-if="!isListView">
                    <div style="display: flex; flex-direction: column; gap: 20px;">
                        <template x-for="(sec, secIdx) in selected" :key="secIdx">
                            <div x-show="sec.fields && sec.fields.length > 0"
                                 style="border: 1px solid #e2e8f0; border-radius: 12px; background: #fff; padding: 16px; box-shadow: 0 1px 3px 0 rgba(0,0,0,0.02); display: flex; flex-direction: column; gap: 14px;">
                                
                                {{-- Section Title --}}
                                <div style="border-bottom: 1px solid #f1f5f9; padding-bottom: 8px;">
                                    <h4 style="font-size: 12px; font-weight: 700; color: #0f172a; margin: 0;" x-text="sec.title || 'Untitled Section'"></h4>
                                </div>
        
                                {{-- Section Grid --}}
                                <div style="display: grid; gap: 16px;"
                                     :style="'grid-template-columns: repeat(' + (sec.columns || 2) + ', minmax(0, 1fr))'">
                                    
                                    <template x-for="field in sec.fields" :key="field">
                                        <div style="display: flex; flex-direction: column; gap: 4px; min-width: 0;"
                                             :style="(sec.columns > 1 && field.toLowerCase().includes('desc') ? 'grid-column: span ' + (sec.columns) : '')">
                                            
                                            {{-- Mock Label --}}
                                            <label style="font-size: 11px; font-weight: 600; color: #475569; display: flex; align-items: center; gap: 3px;">
                                                <span x-text="field.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())"></span>
                                                <span x-show="field.toLowerCase().includes('id') || field.toLowerCase().includes('name')" style="color: #ef4444;">*</span>
                                            </label>
        
                                            {{-- Mock Control based on name heuristics --}}
                                            <div>
                                                {{-- Toggle / Switch mockup --}}
                                                <template x-if="field.toLowerCase().includes('is_') || field.toLowerCase().includes('active') || field.toLowerCase().includes('status') || field.toLowerCase().includes('enable')">
                                                    <div style="display: flex; align-items: center; height: 32px;">
                                                        <div style="width: 36px; height: 20px; border-radius: 9999px; background: #22c55e; padding: 2px; position: relative;">
                                                            <span style="position: absolute; right: 2px; top: 2px; width: 16px; height: 16px; border-radius: 9999px; background: #fff; box-shadow: 0 1px 2px 0 rgba(0,0,0,0.1);"></span>
                                                        </div>
                                                    </div>
                                                </template>
        
                                                {{-- Dropdown / Select mockup --}}
                                                <template x-if="!(field.toLowerCase().includes('is_') || field.toLowerCase().includes('active') || field.toLowerCase().includes('status') || field.toLowerCase().includes('enable')) && (field.toLowerCase().includes('_id') || field.toLowerCase().includes('type') || field.toLowerCase().includes('category') || field.toLowerCase().includes('gender') || field.toLowerCase().includes('mode'))">
                                                    <div style="display: flex; align-items: center; justify-content: space-between; border: 1px solid #cbd5e1; border-radius: 8px; padding: 6px 10px; height: 32px; background: #f8fafc; font-size: 11px; color: #94a3b8; box-sizing: border-box;">
                                                        <span>Select option...</span>
                                                        <svg style="width: 12px; height: 12px; color: #64748b;" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                                        </svg>
                                                    </div>
                                                </template>
        
                                                {{-- Textarea mockup --}}
                                                <template x-if="!(field.toLowerCase().includes('is_') || field.toLowerCase().includes('active') || field.toLowerCase().includes('status') || field.toLowerCase().includes('enable')) && !(field.toLowerCase().includes('_id') || field.toLowerCase().includes('type') || field.toLowerCase().includes('category') || field.toLowerCase().includes('gender') || field.toLowerCase().includes('mode')) && (field.toLowerCase().includes('desc') || field.toLowerCase().includes('note') || field.toLowerCase().includes('about') || field.toLowerCase().includes('address') || field.toLowerCase().includes('json') || field.toLowerCase().includes('custom_'))">
                                                    <div style="border: 1px solid #cbd5e1; border-radius: 8px; min-height: 56px; padding: 6px 10px; background: #fff; font-size: 11px; color: #cbd5e1; box-sizing: border-box;">
                                                        Enter detailed details...
                                                    </div>
                                                </template>
        
                                                {{-- Normal Input field --}}
                                                <template x-if="!(field.toLowerCase().includes('is_') || field.toLowerCase().includes('active') || field.toLowerCase().includes('status') || field.toLowerCase().includes('enable')) && !(field.toLowerCase().includes('_id') || field.toLowerCase().includes('type') || field.toLowerCase().includes('category') || field.toLowerCase().includes('gender') || field.toLowerCase().includes('mode')) && !(field.toLowerCase().includes('desc') || field.toLowerCase().includes('note') || field.toLowerCase().includes('about') || field.toLowerCase().includes('address') || field.toLowerCase().includes('json') || field.toLowerCase().includes('custom_'))">
                                                    <div style="border: 1px solid #cbd5e1; border-radius: 8px; height: 32px; padding: 6px 10px; background: #fff; font-size: 11px; color: #cbd5e1; display: flex; align-items: center; box-sizing: border-box;">
                                                        <span x-text="'Enter ' + field.replace(/_/g, ' ') + '...'"></span>
                                                    </div>
                                                </template>
                                            </div>
                                            
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>
            </div>
            
        </div>
    </div>
</div>
