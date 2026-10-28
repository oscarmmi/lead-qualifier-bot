<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, ref } from 'vue';

type ChatTurn = { role: 'user' | 'assistant'; content: string };
type LeadState = {
    id: string;
    status: 'chatting' | 'qualified';
    score: number | null;
    grade: 'A' | 'B' | 'C' | 'D' | null;
    budget_band: string | null;
    authority: string | null;
    need_summary: string | null;
    timeline: string | null;
};
type DemoExample = {
    name: string;
    score: number;
    grade: 'A' | 'B' | 'C' | 'D';
    need_summary: string;
    next_step: string;
};

const props = defineProps<{ examples: DemoExample[]; llmMode: string }>();
const messages = ref<ChatTurn[]>([
    {
        role: 'assistant',
        content:
            'Hi there! What are you hoping to improve or solve? A sentence is plenty.',
    },
]);
const lead = ref<LeadState | null>(null);
const draft = ref('');
const sending = ref(false);
const error = ref('');
const thread = ref<HTMLElement | null>(null);
const suggestions = ['I need a clinic chatbot', 'Just browsing'];
const slotItems = computed(() => [
    {
        key: 'budget_band',
        label: 'Budget',
        value: lead.value?.budget_band,
        display: budgetLabel(lead.value?.budget_band),
    },
    {
        key: 'authority',
        label: 'Authority',
        value: lead.value?.authority,
        display: authorityLabel(lead.value?.authority),
    },
    {
        key: 'need_summary',
        label: 'Need',
        value: lead.value?.need_summary,
        display: lead.value?.need_summary,
    },
    {
        key: 'timeline',
        label: 'Timeline',
        value: lead.value?.timeline,
        display: timelineLabel(lead.value?.timeline),
    },
]);
const completedSlots = computed(
    () => slotItems.value.filter((slot) => Boolean(slot.value)).length,
);
const recommendation = computed(() => {
    switch (lead.value?.grade) {
        case 'A':
            return 'Schedule a discovery call this week.';
        case 'B':
            return 'Offer a tailored product walkthrough.';
        case 'C':
            return 'Send a concise overview and check back soon.';
        case 'D':
            return 'Share a self-guided resource; no sales follow-up needed yet.';
        default:
            return '';
    }
});

function budgetLabel(value?: string | null): string {
    return (
        (
            {
                under_1k: 'Under $1k',
                '1k_5k': '$1k–$5k',
                '5k_15k': '$5k–$15k',
                '15k_plus': '$15k+',
            } as Record<string, string>
        )[value ?? ''] ?? ''
    );
}

function authorityLabel(value?: string | null): string {
    return (
        (
            {
                decision_maker: 'Decision-maker',
                co_decision_maker: 'Shared decision',
                influencer: 'Evaluator',
            } as Record<string, string>
        )[value ?? ''] ?? ''
    );
}

function timelineLabel(value?: string | null): string {
    return (
        (
            {
                immediate: 'As soon as possible',
                within_3_months: 'Within 3 months',
                '3_6_months': '3–6 months',
                '6_plus': '6+ months',
            } as Record<string, string>
        )[value ?? ''] ?? ''
    );
}

function appendMessage(turn: ChatTurn) {
    messages.value.push(turn);
    void nextTick(() => {
        if (thread.value) thread.value.scrollTop = thread.value.scrollHeight;
    });
}

async function sendMessage(value = draft.value) {
    const message = value.trim();
    if (!message || sending.value || lead.value?.status === 'qualified') return;

    error.value = '';
    sending.value = true;
    draft.value = '';
    appendMessage({ role: 'user', content: message });

    try {
        const csrf =
            document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
                ?.content ?? '';
        const response = await fetch('/chat', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrf,
            },
            body: JSON.stringify({ lead_id: lead.value?.id ?? null, message }),
        });

        if (!response.ok) {
            const payload = await response.json().catch(() => ({}));
            throw new Error(
                payload.message ??
                    'That message could not be sent. Please try again.',
            );
        }

        const payload = await response.json();
        lead.value = payload.lead as LeadState;
        localStorage.setItem('lead-qualifier-conversation', lead.value.id);
        appendMessage({ role: 'assistant', content: payload.reply });
    } catch (cause) {
        messages.value.pop();
        draft.value = message;
        error.value =
            cause instanceof Error
                ? cause.message
                : 'Connection issue. Please try again.';
    } finally {
        sending.value = false;
    }
}

async function resumeConversation() {
    const savedId = localStorage.getItem('lead-qualifier-conversation');
    if (!savedId) return;

    try {
        const response = await fetch(`/chat/${encodeURIComponent(savedId)}`, {
            headers: { Accept: 'application/json' },
        });
        if (!response.ok) throw new Error('Conversation not found');
        const payload = await response.json();
        lead.value = payload.lead as LeadState;
        if (payload.turns.length > 0)
            messages.value = payload.turns as ChatTurn[];
        await nextTick();
        if (thread.value) thread.value.scrollTop = thread.value.scrollHeight;
    } catch {
        localStorage.removeItem('lead-qualifier-conversation');
    }
}

function restartConversation() {
    localStorage.removeItem('lead-qualifier-conversation');
    lead.value = null;
    messages.value = [
        {
            role: 'assistant',
            content:
                'Hi there! What are you hoping to improve or solve? A sentence is plenty.',
        },
    ];
    error.value = '';
    void nextTick(() => {
        if (thread.value) thread.value.scrollTop = 0;
    });
}

onMounted(resumeConversation);
</script>

<template>
    <Head title="Lead qualifier">
        <meta
            name="description"
            content="A conversational BANT lead qualification demo, powered by Laravel and an optional Gemini extraction layer."
        />
    </Head>

    <div class="min-h-screen bg-[#f4f7f6] text-[#172522]">
        <header class="border-b border-[#e5ebe8] bg-white/80">
            <div
                class="mx-auto flex max-w-[1440px] items-center justify-between px-5 py-4 sm:px-8"
            >
                <a
                    href="/"
                    class="flex items-center gap-3"
                    aria-label="Signal home"
                >
                    <span
                        class="grid size-10 place-items-center rounded-xl bg-[#143b32] text-white shadow-sm"
                    >
                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            class="size-5"
                            aria-hidden="true"
                        >
                            <path
                                d="M4 17.5 9.2 12l3.2 3 7.6-8"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            />
                            <path
                                d="M15.5 7H20v4.5"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            />
                        </svg>
                    </span>
                    <span>
                        <span class="block text-sm font-bold tracking-tight"
                            >Signal</span
                        >
                        <span
                            class="block text-[11px] font-medium text-[#788681]"
                            >LEAD QUALIFIER</span
                        >
                    </span>
                </a>
                <div
                    class="flex items-center gap-2 rounded-full border border-[#e3ebe7] bg-white px-3 py-1.5 text-xs font-medium text-[#50635d]"
                >
                    <span
                        class="size-2 rounded-full"
                        :class="
                            llmMode === 'Gemini connected'
                                ? 'bg-emerald-500'
                                : 'bg-[#daa64a]'
                        "
                    ></span>
                    {{ llmMode }}
                </div>
            </div>
        </header>

        <main
            class="mx-auto grid max-w-[1440px] gap-7 px-5 py-8 sm:px-8 lg:grid-cols-[minmax(0,1fr)_360px] lg:gap-10 lg:py-12"
        >
            <section class="min-w-0">
                <div class="mb-7 max-w-2xl">
                    <p
                        class="mb-3 text-xs font-bold tracking-[0.19em] text-[#39806d] uppercase"
                    >
                        A thoughtful first conversation
                    </p>
                    <h1
                        class="text-3xl font-semibold tracking-[-0.04em] sm:text-[42px] sm:leading-[1.12]"
                    >
                        Turn a chat into a clear next step.
                    </h1>
                    <p class="mt-4 max-w-xl text-base leading-7 text-[#687a74]">
                        Signal asks one question at a time, listens for the
                        buying signals that matter, and gives your team a useful
                        recommendation.
                    </p>
                </div>

                <div
                    class="overflow-hidden rounded-[22px] border border-[#e2e9e6] bg-white shadow-[0_18px_60px_-38px_rgba(29,65,54,0.32)]"
                >
                    <div
                        class="flex flex-wrap items-center justify-between gap-4 border-b border-[#edf1ef] px-5 py-4 sm:px-7"
                    >
                        <div class="flex items-center gap-3">
                            <span
                                class="grid size-10 place-items-center rounded-full bg-[#e7f2ed] text-[#28634f]"
                            >
                                <svg
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    class="size-5"
                                    aria-hidden="true"
                                >
                                    <path
                                        d="M12 3.5v3m0 11v3m8.5-8.5h-3m-11 0h-3m14.01-6.01-2.12 2.12m-7.78 7.78-2.12 2.12m12.02 0-2.12-2.12m-7.78-7.78L6.49 6.49"
                                        stroke="currentColor"
                                        stroke-width="1.7"
                                        stroke-linecap="round"
                                    />
                                    <circle
                                        cx="12"
                                        cy="12"
                                        r="4"
                                        stroke="currentColor"
                                        stroke-width="1.7"
                                    />
                                </svg>
                            </span>
                            <div>
                                <h2 class="text-sm font-semibold">
                                    Qualification assistant
                                </h2>
                                <p class="mt-0.5 text-xs text-[#84928d]">
                                    A quick, private conversation · about 1
                                    minute
                                </p>
                            </div>
                        </div>
                        <span
                            class="rounded-full bg-[#f1f6f3] px-3 py-1.5 text-xs font-semibold text-[#438069]"
                            >{{ completedSlots }} of 4 signals</span
                        >
                    </div>

                    <div
                        class="flex flex-wrap gap-2 border-b border-[#edf1ef] px-5 py-3 sm:px-7"
                        aria-label="Qualification progress"
                    >
                        <div
                            v-for="slot in slotItems"
                            :key="slot.key"
                            class="flex items-center gap-2 rounded-full px-3 py-1.5 text-xs font-medium"
                            :class="
                                slot.value
                                    ? 'bg-[#e9f4ee] text-[#25674d]'
                                    : 'bg-[#f5f7f6] text-[#8b9893]'
                            "
                        >
                            <span
                                class="grid size-4 place-items-center rounded-full"
                                :class="
                                    slot.value
                                        ? 'bg-[#4a9470] text-white'
                                        : 'border border-[#cbd4d0]'
                                "
                            >
                                <svg
                                    v-if="slot.value"
                                    viewBox="0 0 16 16"
                                    fill="none"
                                    class="size-3"
                                    aria-hidden="true"
                                >
                                    <path
                                        d="m4 8 2.5 2.5L12 5"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                    />
                                </svg>
                            </span>
                            {{ slot.label }}
                        </div>
                    </div>

                    <div
                        ref="thread"
                        class="chat-thread flex h-[390px] flex-col gap-5 overflow-y-auto px-5 py-6 sm:h-[430px] sm:px-7"
                        aria-live="polite"
                        aria-label="Conversation"
                    >
                        <div
                            v-for="(turn, index) in messages"
                            :key="`${index}-${turn.role}`"
                            class="flex items-end gap-2.5"
                            :class="
                                turn.role === 'user'
                                    ? 'justify-end'
                                    : 'justify-start'
                            "
                        >
                            <span
                                v-if="turn.role === 'assistant'"
                                class="mb-0.5 grid size-7 shrink-0 place-items-center rounded-full bg-[#e7f2ed] text-[10px] font-bold text-[#28634f]"
                                >S</span
                            >
                            <div
                                class="max-w-[86%] rounded-2xl px-4 py-3 text-sm leading-6 whitespace-pre-wrap sm:max-w-[76%]"
                                :class="
                                    turn.role === 'user'
                                        ? 'rounded-br-md bg-[#173e34] text-white'
                                        : 'rounded-bl-md bg-[#f1f5f3] text-[#364841]'
                                "
                            >
                                {{ turn.content }}
                            </div>
                            <span
                                v-if="turn.role === 'user'"
                                class="mb-0.5 grid size-7 shrink-0 place-items-center rounded-full bg-[#e8e8de] text-[10px] font-bold text-[#65706a]"
                                >Y</span
                            >
                        </div>
                        <div v-if="sending" class="flex items-end gap-2.5">
                            <span
                                class="grid size-7 place-items-center rounded-full bg-[#e7f2ed] text-[10px] font-bold text-[#28634f]"
                                >S</span
                            >
                            <div
                                class="flex gap-1 rounded-2xl rounded-bl-md bg-[#f1f5f3] px-4 py-4"
                                aria-label="Assistant is thinking"
                            >
                                <span class="typing-dot"></span
                                ><span class="typing-dot"></span
                                ><span class="typing-dot"></span>
                            </div>
                        </div>
                        <div
                            v-if="lead?.status === 'qualified'"
                            class="score-card mt-1 ml-9 max-w-[390px] rounded-2xl border border-[#d5e8dc] bg-[#f5fbf7] p-4 sm:p-5"
                        >
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p
                                        class="text-[11px] font-bold tracking-[0.14em] text-[#558269] uppercase"
                                    >
                                        Qualification complete
                                    </p>
                                    <p
                                        class="mt-1 text-sm leading-6 text-[#52665b]"
                                    >
                                        {{ lead.need_summary }}
                                    </p>
                                </div>
                                <span
                                    class="grid size-12 shrink-0 place-items-center rounded-xl bg-[#173e34] text-lg font-bold text-white"
                                    >{{ lead.grade }}</span
                                >
                            </div>
                            <div
                                class="mt-4 flex items-center justify-between text-xs font-semibold text-[#667a6e]"
                            >
                                <span>Lead score</span
                                ><span>{{ lead.score }} / 100</span>
                            </div>
                            <div
                                class="mt-2 h-2 overflow-hidden rounded-full bg-[#dfebe3]"
                            >
                                <div
                                    class="h-full rounded-full bg-[#4a9470] transition-all duration-700"
                                    :style="{ width: `${lead.score}%` }"
                                ></div>
                            </div>
                            <div class="mt-4 rounded-xl bg-white px-3.5 py-3">
                                <p
                                    class="text-[10px] font-bold tracking-[0.13em] text-[#8b9890] uppercase"
                                >
                                    Recommended next step
                                </p>
                                <p
                                    class="mt-1 text-sm font-semibold text-[#355747]"
                                >
                                    {{ recommendation }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-[#edf1ef] px-5 py-4 sm:px-7">
                        <div
                            v-if="!lead && messages.length === 1"
                            class="mb-3 flex flex-wrap gap-2"
                        >
                            <button
                                v-for="suggestion in suggestions"
                                :key="suggestion"
                                type="button"
                                class="rounded-full border border-[#dce8e2] bg-white px-3.5 py-2 text-xs font-medium text-[#47715d] transition hover:border-[#85b69b] hover:bg-[#f4faf6] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2d765b]"
                                @click="sendMessage(suggestion)"
                            >
                                {{ suggestion }}
                            </button>
                        </div>
                        <p
                            v-if="error"
                            role="alert"
                            class="mb-2 text-xs font-medium text-rose-700"
                        >
                            {{ error }}
                        </p>
                        <div
                            v-if="lead?.status === 'qualified'"
                            class="mb-3 flex items-center justify-between gap-3 text-xs text-[#7d8b84]"
                        >
                            <span>Ready to try another conversation?</span>
                            <button
                                type="button"
                                class="font-semibold text-[#347256] underline decoration-[#b9d6c4] underline-offset-4 hover:text-[#1d5639]"
                                @click="restartConversation"
                            >
                                Start a fresh chat
                            </button>
                        </div>
                        <form
                            class="flex items-center gap-2 rounded-2xl border border-[#dfe7e3] bg-[#fafcfb] p-1.5 pl-4 transition focus-within:border-[#8db5a0] focus-within:ring-2 focus-within:ring-[#8db5a0]/20"
                            @submit.prevent="sendMessage()"
                        >
                            <input
                                v-model="draft"
                                :disabled="
                                    sending || lead?.status === 'qualified'
                                "
                                type="text"
                                maxlength="1000"
                                autocomplete="off"
                                aria-label="Your message"
                                placeholder="Type your answer…"
                                class="min-w-0 flex-1 border-0 bg-transparent py-2 text-sm text-[#283b33] outline-none placeholder:text-[#a0aca5] focus:ring-0 disabled:cursor-not-allowed"
                            />
                            <button
                                type="submit"
                                :disabled="
                                    sending ||
                                    !draft.trim() ||
                                    lead?.status === 'qualified'
                                "
                                class="grid size-10 shrink-0 place-items-center rounded-xl bg-[#173e34] text-white transition hover:bg-[#235849] disabled:cursor-not-allowed disabled:opacity-40"
                                aria-label="Send message"
                            >
                                <svg
                                    viewBox="0 0 20 20"
                                    fill="none"
                                    class="size-4"
                                    aria-hidden="true"
                                >
                                    <path
                                        d="m3 10 13-6-4.5 13-2.4-5.4L3 10Z"
                                        stroke="currentColor"
                                        stroke-width="1.5"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                    />
                                    <path
                                        d="m9.1 11.6 3.5-3.5"
                                        stroke="currentColor"
                                        stroke-width="1.5"
                                        stroke-linecap="round"
                                    />
                                </svg>
                            </button>
                        </form>
                        <p
                            class="mt-2.5 text-center text-[11px] text-[#9aa59f]"
                        >
                            No account needed · Your conversation stays in this
                            demo
                        </p>
                    </div>
                </div>
            </section>

            <aside class="space-y-5 lg:pt-[132px]">
                <section
                    class="rounded-[20px] border border-[#e2e9e6] bg-white p-5 shadow-[0_14px_45px_-38px_rgba(29,65,54,0.3)] sm:p-6"
                >
                    <div class="flex items-center justify-between">
                        <div>
                            <p
                                class="text-[11px] font-bold tracking-[0.16em] text-[#85938d] uppercase"
                            >
                                What Signal listens for
                            </p>
                            <h2
                                class="mt-1 text-lg font-semibold tracking-tight"
                            >
                                Four buying signals
                            </h2>
                        </div>
                        <span
                            class="grid size-9 place-items-center rounded-xl bg-[#f0f5f2] text-[#4f7d67]"
                        >
                            <svg
                                viewBox="0 0 20 20"
                                fill="none"
                                class="size-4"
                                aria-hidden="true"
                            >
                                <path
                                    d="M3 15.5h14M4.5 13l3.1-3.4 2.3 1.8 5.6-6"
                                    stroke="currentColor"
                                    stroke-width="1.6"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                />
                            </svg>
                        </span>
                    </div>
                    <div class="mt-5 space-y-4">
                        <div
                            v-for="(slot, index) in slotItems"
                            :key="slot.key"
                            class="flex gap-3"
                        >
                            <span
                                class="grid size-7 shrink-0 place-items-center rounded-lg text-xs font-bold"
                                :class="
                                    slot.value
                                        ? 'bg-[#e8f4ec] text-[#397653]'
                                        : 'bg-[#f3f5f4] text-[#96a19b]'
                                "
                                >0{{ index + 1 }}</span
                            >
                            <div class="min-w-0 pt-0.5">
                                <p class="text-sm font-semibold">
                                    {{ slot.label }}
                                </p>
                                <p
                                    class="mt-0.5 text-xs leading-5 text-[#85928c]"
                                >
                                    {{
                                        slot.key === 'budget_band'
                                            ? 'Investment range'
                                            : slot.key === 'authority'
                                              ? 'Decision-making role'
                                              : slot.key === 'need_summary'
                                                ? 'Problem to solve'
                                                : 'Buying timeframe'
                                    }}
                                </p>
                            </div>
                            <span
                                class="mt-2 ml-auto size-2 shrink-0 rounded-full"
                                :class="
                                    slot.value ? 'bg-[#57a275]' : 'bg-[#d8dfdb]'
                                "
                                :aria-label="
                                    slot.value ? 'Captured' : 'Not yet captured'
                                "
                            ></span>
                        </div>
                    </div>
                    <div
                        class="mt-5 rounded-xl bg-[#f7f9f8] px-3.5 py-3 text-xs leading-5 text-[#74837b]"
                    >
                        One clear question at a time. No long forms and no
                        guessing at the score.
                    </div>
                </section>

                <section
                    class="rounded-[20px] border border-[#e2e9e6] bg-white p-5 shadow-[0_14px_45px_-38px_rgba(29,65,54,0.3)] sm:p-6"
                >
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p
                                class="text-[11px] font-bold tracking-[0.16em] text-[#85938d] uppercase"
                            >
                                Example data
                            </p>
                            <h2
                                class="mt-1 text-lg font-semibold tracking-tight"
                            >
                                Recent qualifications
                            </h2>
                        </div>
                        <span
                            class="rounded-full bg-[#f3f6f4] px-2.5 py-1 text-[11px] font-semibold text-[#718079]"
                            >{{ examples.length }} sample leads</span
                        >
                    </div>
                    <div class="mt-4 divide-y divide-[#eef1ef]">
                        <div
                            v-for="example in examples"
                            :key="example.name"
                            class="flex gap-3 py-3.5 first:pt-0 last:pb-0"
                        >
                            <span
                                class="grid size-9 shrink-0 place-items-center rounded-full bg-[#edf3ef] text-xs font-bold text-[#567466]"
                                >{{
                                    example.name
                                        .split(' ')
                                        .map((part) => part[0])
                                        .join('')
                                }}</span
                            >
                            <div class="min-w-0 flex-1">
                                <div
                                    class="flex items-center justify-between gap-2"
                                >
                                    <p class="truncate text-sm font-semibold">
                                        {{ example.name }}
                                    </p>
                                    <span
                                        class="rounded-md px-2 py-0.5 text-xs font-bold"
                                        :class="
                                            example.grade === 'A'
                                                ? 'bg-[#e6f3e9] text-[#287443]'
                                                : example.grade === 'B'
                                                  ? 'bg-[#eaf1fa] text-[#426c9c]'
                                                  : 'bg-[#f3f1ed] text-[#777168]'
                                        "
                                        >{{ example.grade }} ·
                                        {{ example.score }}</span
                                    >
                                </div>
                                <p
                                    class="mt-1 line-clamp-2 text-xs leading-5 text-[#829089]"
                                >
                                    {{ example.need_summary }}
                                </p>
                            </div>
                        </div>
                        <p
                            v-if="examples.length === 0"
                            class="py-4 text-sm text-[#85928c]"
                        >
                            Run the database seeder to load sample leads.
                        </p>
                    </div>
                    <p
                        class="mt-4 border-t border-[#eef1ef] pt-3 text-[10px] leading-4 text-[#a0aaa5]"
                    >
                        Fictional Northwind Labs examples · sample data only
                    </p>
                </section>
                <p
                    class="px-1 text-center text-[11px] leading-5 text-[#9aa69f]"
                >
                    A portfolio demo · no CRM connection, tracking pixels, or
                    customer data
                </p>
            </aside>
        </main>
    </div>
</template>
