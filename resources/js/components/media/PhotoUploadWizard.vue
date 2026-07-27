<template>
    <div class="card shadow border-0 rounded-3">
        <div class="card-body p-4">
            <div class="d-flex flex-column flex-md-row justify-content-between gap-2 mb-3">
                <div>
                    <h2 class="card-title h4 fw-bold mb-1">Photos Upload</h2>
                    <p class="text-muted mb-0">
                        Upload one image at a time. Each photo needs its own description and tags.
                    </p>
                </div>

                <span class="badge rounded-pill align-self-start"
                    :class="photographyType === 'professional' ? 'text-bg-primary' : 'text-bg-secondary'">
                    {{ photographyLabel }}
                </span>
            </div>

            <div v-if="photographyType === 'professional'" class="alert alert-info py-2">
                Professional photos must be at least 720px by 720px and pass live backend quality validation.
            </div>

            <div
                class="border border-2 border-dashed border-secondary-subtle rounded-3 p-4 text-center bg-body-tertiary mb-4"
                @dragover.prevent
                @drop.prevent="handleDrop"
            >
                <input
                    ref="fileInput"
                    type="file"
                    accept="image/png,image/jpeg,image/webp"
                    hidden
                    @change="handleSelect"
                />

                <p class="fs-5 fw-medium text-secondary mb-2">Choose one photo</p>
                <p class="text-muted small mb-3">
                    JPG, PNG, or WEBP. Maximum file size is 20 MB.
                </p>

                <button
                    type="button"
                    class="btn btn-primary btn-md px-4 py-2 rounded-pill"
                    :disabled="!photographyType || hasReachedLimit || uploading"
                    @click="choosePhoto"
                >
                    {{ uploading ? "Checking..." : "Add another photo" }}
                </button>

                <small class="text-muted d-block mt-2">
                    {{ items.length }} / {{ maxPhotos }} photos
                </small>
            </div>

            <div v-if="items.length === 0" class="text-muted text-center py-4">
                No photos added yet.
            </div>

            <div v-else class="d-flex flex-column gap-3">
                <div
                    v-for="(item, index) in items"
                    :key="item.id"
                    class="border rounded-3 p-3 bg-white"
                >
                    <div class="row g-3">
                        <div class="col-12 col-md-4">
                            <div class="position-relative">
                                <img
                                    v-if="previewSource(item)"
                                    :src="previewSource(item)"
                                    :alt="'photo ' + (index + 1)"
                                    class="img-fluid rounded-3 shadow-sm w-100"
                                    style="height: 210px; object-fit: cover"
                                />

                                <div
                                    v-else
                                    class="rounded-3 bg-body-tertiary d-flex align-items-center justify-content-center text-muted"
                                    style="height: 210px"
                                >
                                    Preview unavailable
                                </div>

                                <button
                                    type="button"
                                    class="btn btn-sm btn-danger position-absolute top-0 end-0 m-2 rounded-circle"
                                    style="width: 30px; height: 30px; line-height: 1; padding: 0"
                                    title="Remove photo"
                                    @click="removePhoto(index)"
                                >
                                    x
                                </button>
                            </div>

                            <div class="d-flex flex-wrap gap-2 mt-2">
                                <span class="badge rounded-pill" :class="statusBadgeClass(item.status)">
                                    {{ statusLabel(item.status) }}
                                </span>

                                <span v-if="item.metrics.width && item.metrics.height" class="badge text-bg-light">
                                    {{ item.metrics.width }} x {{ item.metrics.height }}
                                </span>

                                <span v-if="item.metrics.quality_score !== null" class="badge text-bg-light">
                                    Quality {{ item.metrics.quality_score }}
                                </span>
                            </div>
                        </div>

                        <div class="col-12 col-md-8">
                            <div class="mb-3">
                                <label class="form-label fw-medium">
                                    Photo description <span class="text-danger">*</span>
                                </label>
                                <textarea
                                    v-model="item.description"
                                    class="form-control rounded-3"
                                    rows="3"
                                    placeholder="Describe this photo"
                                    @input="emitUpdate"
                                ></textarea>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-medium d-flex justify-content-between">
                                    <span>Photo tags <span class="text-danger">*</span></span>
                                    <small class="text-muted">{{ item.tags.length }} / {{ maxTags }}</small>
                                </label>

                                <div class="position-relative">
                                    <button
                                        type="button"
                                        class="form-control rounded-3 text-start d-flex flex-wrap align-items-center gap-2"
                                        :disabled="loadingTags"
                                        @click="togglePhotoTagsDropdown(item)"
                                    >
                                        <span v-if="loadingTags" class="text-muted">
                                            Loading tags...
                                        </span>

                                        <template v-else-if="item.tags.length">
                                            <span
                                                v-for="tag in item.tags"
                                                :key="tag.id"
                                                class="badge rounded-pill d-inline-flex align-items-center gap-1"
                                                :class="tag.isNew ? 'text-bg-success' : 'text-bg-primary'"
                                            >
                                                #{{ tag.name }}
                                                <span role="button" class="ms-1" @click.stop="removePhotoTag(item, tag)">
                                                    x
                                                </span>
                                            </span>
                                        </template>

                                        <span v-else class="text-muted">
                                            Select or create photo tags
                                        </span>
                                    </button>

                                    <div
                                        v-if="item.showTagsDropdown && !loadingTags"
                                        class="position-absolute z-3 w-100 mt-1 bg-white border rounded-3 shadow p-2"
                                        style="max-height: 260px; overflow-y: auto"
                                    >
                                        <input
                                            v-model="item.tagSearch"
                                            type="text"
                                            class="form-control form-control-sm rounded-3 mb-2"
                                            placeholder="Search or type new tag"
                                            @keydown.enter.prevent="canAddNewPhotoTag(item) && addNewPhotoTag(item)"
                                            @input="emitUpdate"
                                        />

                                        <button
                                            v-if="canAddNewPhotoTag(item)"
                                            type="button"
                                            class="btn btn-outline-success btn-sm w-100 mb-2 text-start"
                                            @click="addNewPhotoTag(item)"
                                        >
                                            + Add "{{ normalizeTagName(item.tagSearch) }}"
                                        </button>

                                        <button
                                            v-if="item.tags.length"
                                            type="button"
                                            class="btn btn-link btn-sm text-danger text-decoration-none px-0 mb-1"
                                            @click="clearPhotoTags(item)"
                                        >
                                            Clear selected tags
                                        </button>

                                        <label
                                            v-for="tag in filteredPhotoTags(item)"
                                            :key="tag.id"
                                            class="d-flex align-items-center gap-2 px-2 py-2 rounded-3"
                                            style="cursor: pointer"
                                        >
                                            <input
                                                class="form-check-input m-0"
                                                type="checkbox"
                                                :checked="isPhotoTagSelected(item, tag.id)"
                                                @change="togglePhotoTag(item, tag)"
                                            />
                                            <span>#{{ tagName(tag) }}</span>
                                        </label>

                                        <div
                                            v-if="hasPhotoTagSearch(item) && filteredPhotoTags(item).length === 0 && !canAddNewPhotoTag(item)"
                                            class="text-muted small px-2 py-2"
                                        >
                                            No tags found
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row g-3">
                                <div class="col-12 col-sm-6">
                                    <label class="form-label fw-medium">Your price</label>
                                    <input
                                        v-model.number="item.custom_price"
                                        type="number"
                                        min="1"
                                        step="1"
                                        class="form-control rounded-3"
                                        placeholder="Enter your price"
                                        @input="emitUpdate"
                                    />
                                    <small v-if="item.suggested_price" class="text-muted">
                                        Suggested: ${{ item.suggested_price }}
                                    </small>
                                </div>

                                <div class="col-12 col-sm-6">
                                    <label class="form-label fw-medium">Validation details</label>
                                    <div class="small text-muted">
                                        {{ item.validationMessage || "Waiting for validation" }}
                                    </div>
                                    <div v-if="item.metrics.sharpness_score !== null" class="small text-muted">
                                        Sharpness {{ item.metrics.sharpness_score }},
                                        Blur {{ item.metrics.blur_score }}
                                    </div>
                                </div>
                            </div>

                            <div v-if="item.errors.length" class="alert alert-danger py-2 mt-3 mb-0">
                                <div class="fw-semibold mb-1">Rejection reasons</div>
                                <ul class="mb-0 ps-3">
                                    <li v-for="error in item.errors" :key="error">{{ error }}</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onUnmounted, ref, watch } from "vue";
import { MediaService } from "../../services/MediaService/MediaService";

const props = defineProps({
    modelValue: {
        type: Array,
        default: () => [],
    },
    photographyType: {
        type: String,
        default: "",
    },
    maxPhotos: {
        type: Number,
        default: 8,
    },
    maxTags: {
        type: Number,
        default: 10,
    },
    availableTags: {
        type: Array,
        default: () => [],
    },
    loadingTags: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(["update:modelValue"]);

const fileInput = ref(null);
const uploading = ref(false);
const items = ref([]);
const objectUrls = new Set();
let ignoreNextModelSync = false;

const allowedTypes = ["image/jpeg", "image/png", "image/webp"];
const maxFileSize = 20 * 1024 * 1024;

const hasReachedLimit = computed(() => items.value.length >= props.maxPhotos);

const photographyLabel = computed(() => {
    if (props.photographyType === "professional") return "Professional photography";
    if (props.photographyType === "normal") return "Normal photography";
    return "Select photography type first";
});

watch(
    () => props.modelValue,
    (value) => {
        if (ignoreNextModelSync) {
            ignoreNextModelSync = false;
            return;
        }

        items.value = Array.isArray(value) ? value.map(normalizeMediaItem) : [];
    },
    { immediate: true }
);

watch(
    () => props.photographyType,
    async (newType, oldType) => {
        if (!newType || !oldType || newType === oldType || items.value.length === 0) {
            return;
        }

        await revalidateAll();
    }
);

onUnmounted(() => {
    items.value.forEach(revokePreviewUrl);
    objectUrls.clear();
});

function choosePhoto() {
    if (!props.photographyType) {
        alert("Please select photography type first.");
        return;
    }

    if (hasReachedLimit.value) {
        alert(`You can upload up to ${props.maxPhotos} photos.`);
        return;
    }

    fileInput.value?.click();
}

async function handleSelect(event) {
    const file = event.target.files?.[0];

    if (file) {
        await addFile(file);
    }

    event.target.value = "";
}

async function handleDrop(event) {
    const file = event.dataTransfer.files?.[0];

    if (file) {
        await addFile(file);
    }
}

async function addFile(file) {
    if (!props.photographyType) {
        alert("Please select photography type first.");
        return;
    }

    if (hasReachedLimit.value) {
        alert(`You can upload up to ${props.maxPhotos} photos.`);
        return;
    }

    const item = createPhotoItem(file);
    items.value = [...items.value, item];

    const basicErrors = validateBasicFile(file);

    if (basicErrors.length) {
        item.status = "rejected";
        item.errors = basicErrors;
        item.validationMessage = "Photo rejected";
        emitUpdate();
        return;
    }

    try {
        uploading.value = true;
        await validatePhotoItem(item);
    } catch (error) {
        item.status = "rejected";
        item.errors = [error.message || "Unable to read or validate this photo."];
        item.validationMessage = "Photo rejected";
    } finally {
        uploading.value = false;
        emitUpdate();
    }
}

function createPhotoItem(file) {
    return {
        id: Date.now() + Math.random(),
        file,
        preview_url: createPreviewUrl(file),
        preview: "",
        description: "",
        tags: [],
        tagSearch: "",
        showTagsDropdown: false,
        status: "idle",
        errors: [],
        validationMessage: "",
        metrics: emptyMetrics(),
        suggested_price: "",
        custom_price: "",
    };
}

function createPreviewUrl(file) {
    if (typeof File === "undefined" || !(file instanceof File)) {
        return "";
    }

    const url = URL.createObjectURL(file);
    objectUrls.add(url);

    return url;
}

function previewSource(item) {
    return item?.preview_url || item?.preview || "";
}

function revokePreviewUrl(item) {
    const previewUrl = item?.preview_url;

    if (previewUrl && objectUrls.has(previewUrl)) {
        URL.revokeObjectURL(previewUrl);
        objectUrls.delete(previewUrl);
    }
}

function emptyMetrics() {
    return {
        width: null,
        height: null,
        megapixels: null,
        sharpness_score: null,
        blur_score: null,
        quality_score: null,
        file_size_mb: null,
    };
}

function validateBasicFile(file) {
    const errors = [];

    if (!allowedTypes.includes(file.type)) {
        errors.push("Allowed types are jpg, jpeg, png, and webp.");
    }

    if (file.size > maxFileSize) {
        errors.push("Maximum file size is 20 MB.");
    }

    return errors;
}

async function validatePhotoItem(item) {
    item.status = "checking";
    item.errors = [];
    item.validationMessage = "Checking photo...";
    emitUpdate();

    const fd = new FormData();
    fd.append("photo", item.file);
    fd.append("photography_type", props.photographyType);

    try {
        const response = await MediaService.validatePhoto(fd);
        applyValidationResult(item, response.data);
    } catch (error) {
        if (error.response?.data) {
            applyValidationResult(item, error.response.data);
        } else {
            item.status = "rejected";
            item.validationMessage = "Photo rejected";
            item.errors = ["Photo validation failed. Please try again."];
            item.metrics = emptyMetrics();
        }
    } finally {
        if (item.status === "checking") {
            item.status = "rejected";
            item.validationMessage = "Photo rejected";
            item.errors = ["Photo validation failed. Please try again."];
        }

        emitUpdate();
    }
}

function applyValidationResult(item, result) {
    const accepted = Boolean(result?.accepted) || result?.status === "accepted";
    const metrics = normalizeMetrics(result?.metrics || {});

    item.status = accepted ? "accepted" : "rejected";
    item.validationMessage = result?.message || (accepted ? "Photo accepted" : "Photo rejected");
    item.errors = Array.isArray(result?.errors) ? result.errors : [];
    item.metrics = metrics;

    if (accepted) {
        const suggestedPrice = Number(result?.suggested_price || 0) || calculateSuggestedPrice(metrics);
        item.suggested_price = suggestedPrice;

        if (!item.custom_price) {
            item.custom_price = suggestedPrice;
        }
    }

    if (item.status === "checking") {
        item.status = accepted ? "accepted" : "rejected";
    }
}

function normalizeMetrics(metrics) {
    return {
        width: metrics.width ?? null,
        height: metrics.height ?? null,
        megapixels: metrics.megapixels ?? null,
        sharpness_score: metrics.sharpness_score ?? null,
        blur_score: metrics.blur_score ?? null,
        quality_score: metrics.quality_score ?? null,
        file_size_mb: metrics.file_size_mb ?? null,
    };
}

function calculateSuggestedPrice(metrics) {
    const score = Number(metrics.quality_score || 0);
    const megapixels = Number(metrics.megapixels || 0);
    let price = 10;

    if (score >= 90) price = 80;
    else if (score >= 80) price = 60;
    else if (score >= 70) price = 45;
    else if (score >= 60) price = 30;
    else if (score >= 50) price = 20;

    if (megapixels >= 12) price += 15;
    else if (megapixels >= 8) price += 10;
    else if (megapixels >= 4) price += 5;

    return Math.max(10, Math.round(price));
}

function normalizeMediaItem(item) {
    const source = item || {};
    const previewUrl = source.preview_url || source.preview || "";

    return {
        ...source,
        preview_url: previewUrl,
        tags: Array.isArray(source.tags) ? normalizeSelectedTags(source.tags) : [],
        tagSearch: typeof source.tagSearch === "string" ? source.tagSearch : "",
        showTagsDropdown: Boolean(source.showTagsDropdown),
        custom_price: source.custom_price ?? "",
        suggested_price: source.suggested_price ?? "",
        status: source.status || "idle",
        metrics: source.metrics || emptyMetrics(),
        errors: Array.isArray(source.errors) ? source.errors : [],
    };
}

function normalizeSelectedTags(tags) {
    if (!Array.isArray(tags)) {
        return [];
    }

    return tags
        .map((tag) => {
            if (tag && typeof tag === "object") {
                const name = normalizeTagName(tag.name);

                if (!name) return null;

                return {
                    id: tag.id ?? `new-${Date.now()}-${Math.random()}`,
                    name,
                    isNew: Boolean(tag.isNew),
                };
            }

            const name = normalizeTagName(tag);

            if (!name) return null;

            return {
                id: `new-${Date.now()}-${Math.random()}`,
                name,
                isNew: true,
            };
        })
        .filter(Boolean);
}

function tagName(tag) {
    return tag?.name || tag?.translation?.name || "";
}

function normalizeTagName(value) {
    return String(value || "")
        .replace(/^#+/, "")
        .trim()
        .replace(/\s+/g, " ");
}

function hasPhotoTagSearch(item) {
    return normalizeTagName(item.tagSearch).length > 0;
}

function filteredPhotoTags(item) {
    const search = normalizeTagName(item.tagSearch).toLowerCase();

    if (!search) return [];

    return props.availableTags.filter((tag) =>
        tagName(tag).toLowerCase().includes(search)
    );
}

function isPhotoTagSelected(item, tagId) {
    return item.tags.some(
        (tag) => !tag.isNew && String(tag.id) === String(tagId)
    );
}

function isTagNameSelected(item, name) {
    const normalizedName = normalizeTagName(name).toLowerCase();

    return item.tags.some(
        (tag) => normalizeTagName(tag.name).toLowerCase() === normalizedName
    );
}

function availableTagNameExists(name) {
    const normalizedName = normalizeTagName(name).toLowerCase();

    return props.availableTags.some(
        (tag) => tagName(tag).toLowerCase() === normalizedName
    );
}

function canAddNewPhotoTag(item) {
    const name = normalizeTagName(item.tagSearch);

    if (!name || item.tags.length >= props.maxTags) {
        return false;
    }

    return !isTagNameSelected(item, name) && !availableTagNameExists(name);
}

function togglePhotoTagsDropdown(item) {
    item.showTagsDropdown = !item.showTagsDropdown;
    emitUpdate();
}

function togglePhotoTag(item, tag) {
    if (isPhotoTagSelected(item, tag.id)) {
        removePhotoTag(item, { id: tag.id, isNew: false });
        return;
    }

    if (item.tags.length >= props.maxTags) {
        alert(`Each photo can have up to ${props.maxTags} tags.`);
        return;
    }

    if (isTagNameSelected(item, tagName(tag))) {
        alert("This tag is already selected.");
        return;
    }

    item.tags = [
        ...item.tags,
        {
            id: tag.id,
            name: tagName(tag),
            isNew: false,
        },
    ];

    emitUpdate();
}

function addNewPhotoTag(item) {
    const name = normalizeTagName(item.tagSearch);

    if (!name) return;

    if (!canAddNewPhotoTag(item)) {
        return;
    }

    item.tags = [
        ...item.tags,
        {
            id: `new-${Date.now()}-${Math.random()}`,
            name,
            isNew: true,
        },
    ];

    item.tagSearch = "";
    emitUpdate();
}

function removePhotoTag(item, tag) {
    item.tags = item.tags.filter((selectedTag) => {
        if (tag.isNew) {
            return String(selectedTag.id) !== String(tag.id);
        }

        return selectedTag.isNew || String(selectedTag.id) !== String(tag.id);
    });

    emitUpdate();
}

function clearPhotoTags(item) {
    item.tags = [];
    item.tagSearch = "";
    emitUpdate();
}

function removePhoto(index) {
    revokePreviewUrl(items.value[index]);
    items.value = items.value.filter((_, photoIndex) => photoIndex !== index);
    emitUpdate();
}

async function revalidateAll() {
    for (const item of items.value) {
        if (validateBasicFile(item.file).length === 0) {
            await validatePhotoItem(item);
        }
    }
}

function statusBadgeClass(status) {
    if (status === "accepted") return "text-bg-success";
    if (status === "rejected") return "text-bg-danger";
    if (status === "checking") return "text-bg-warning";
    return "text-bg-secondary";
}

function statusLabel(status) {
    if (status === "accepted") return "accepted";
    if (status === "rejected") return "rejected";
    if (status === "checking") return "checking";
    return "idle";
}

function cloneMediaItemForEmit(item) {
    return {
        ...item,
        tags: Array.isArray(item.tags) ? item.tags.map((tag) => ({ ...tag })) : [],
        metrics: item.metrics ? { ...item.metrics } : {},
        errors: Array.isArray(item.errors) ? [...item.errors] : [],
    };
}

function emitUpdate() {
    const updatedItems = items.value.map(cloneMediaItemForEmit);
    ignoreNextModelSync = true;
    emit("update:modelValue", updatedItems);

    setTimeout(() => {
        ignoreNextModelSync = false;
    }, 0);
}
</script>
