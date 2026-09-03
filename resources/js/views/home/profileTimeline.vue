<template>
    <div class="profile-timeline-page">

        <!-- Header -->
        <section class="timeline-header">

            <div>
                <h1>
                    {{ $t("nav.timeline") }}
                </h1>

                <p>
                    {{ $t("profile.timelineDescription") }}
                </p>
            </div>

            <div class="timeline-icon">
                ⏱
            </div>

        </section>


        <!-- Loading -->
        <section 
            v-if="loading"
            class="timeline-card empty-state"
        >
            Loading...
        </section>


        <!-- Timeline -->
        <section 
            v-else-if="timeline.length"
            class="timeline-card"
        >

            <div
                v-for="item in timeline"
                :key="`${item.type}-${item.data.id}-${item.created_at}`"
                class="timeline-item"
            >

                <div class="timeline-dot"></div>


                <div class="timeline-content">


                    <div class="timeline-top">

                        <h3>
                            {{ item.title }}
                        </h3>


                        <span>
                            {{ formatDate(item.created_at) }}
                        </span>

                    </div>



                    <!-- Event -->
                    <div
                        v-if="item.type === 'event_created'"
                        class="activity-box"
                    >

                        <strong>
                            Event
                        </strong>


                        <p>
                            {{ item.data.title }}
                        </p>


                        <small>
                            ID: {{ item.data.id }}
                        </small>

                    </div>



                    <!-- Future Types -->
                    <div
                        v-else
                        class="activity-box"
                    >

                        <p>
                            {{ item.data }}
                        </p>

                    </div>


                </div>


            </div>


        </section>



        <!-- Empty -->
        <section
            v-else
            class="timeline-card empty-state"
        >

            <div class="empty-icon">
                📭
            </div>


            <h3>
                No activity yet
            </h3>


            <p>
                Your interactions will appear here.
            </p>

        </section>


    </div>
</template>



<script setup>

import { ref, onMounted } from "vue";
import { profileTimeline } from "@/services/profileTimeline/profileTimeline";


const timeline = ref([]);

const loading = ref(false);



const loadTimeline = async () => {

    loading.value = true;


    try {

        const response = await profileTimeline.getTimeline();


        timeline.value = response.data.data || [];


        console.log(
            "Timeline:",
            timeline.value
        );


    } catch(error) {

        console.error(
            "Timeline Error:",
            error
        );


    } finally {

        loading.value = false;

    }

};



const formatDate = (date)=>{

    if(!date)
        return "";


    return new Date(date)
        .toLocaleDateString();

};



onMounted(()=>{

    loadTimeline();

});


</script>




<style scoped>

.profile-timeline-page {

    max-width: 1000px;
    margin: 120px auto 60px;
    padding: 0 20px;

}



.timeline-header {

    display:flex;
    justify-content:space-between;
    align-items:center;

    background:white;

    border:1px solid #e5edf7;

    border-radius:24px;

    padding:32px;

    margin-bottom:32px;

    box-shadow:
    0 15px 40px rgba(13,77,151,.08);

}



.timeline-header h1 {

    margin:0;

    font-size:36px;

    font-weight:900;

    color:#0d4d97;

}



.timeline-header p {

    margin-top:10px;

    color:#64748b;

}



.timeline-icon {

    width:60px;
    height:60px;

    display:flex;
    align-items:center;
    justify-content:center;

    border-radius:50%;

    background:#eaf4ff;

    font-size:28px;

}



.timeline-card {

    background:white;

    border-radius:24px;

    padding:35px;

    box-shadow:
    0 15px 40px rgba(13,77,151,.08);

}



.timeline-item {

    position:relative;

    display:flex;

    gap:20px;

    padding-bottom:30px;

}



.timeline-item:not(:last-child)::before {

    content:"";

    position:absolute;

    left:7px;

    top:18px;

    height:100%;

    width:2px;

    background:#dbeafe;

}



.timeline-dot {

    width:16px;

    height:16px;

    background:#0d4d97;

    border-radius:50%;

    margin-top:5px;

    flex-shrink:0;

}



.timeline-content {

    flex:1;

}



.timeline-top {

    display:flex;

    justify-content:space-between;

    gap:15px;

}



.timeline-top h3 {

    margin:0;

    color:#0f172a;

    font-size:18px;

}



.timeline-top span {

    color:#94a3b8;

    font-size:13px;

}



.activity-box {

    margin-top:15px;

    padding:18px;

    border-radius:16px;

    background:#f8fafc;

    border:1px solid #e2e8f0;

}



.activity-box strong {

    color:#0d4d97;

}



.activity-box p {

    margin:10px 0;

    color:#334155;

}



.empty-state {

    min-height:250px;

    display:flex;

    flex-direction:column;

    align-items:center;

    justify-content:center;

    text-align:center;

}



.empty-icon {

    font-size:50px;

    margin-bottom:15px;

}



.empty-state h3 {

    color:#0f172a;

}



.empty-state p {

    color:#64748b;

}



@media(max-width:700px){

    .timeline-header {

        padding:20px;

    }


    .timeline-header h1 {

        font-size:28px;

    }


    .timeline-top {

        flex-direction:column;

    }

}


</style>