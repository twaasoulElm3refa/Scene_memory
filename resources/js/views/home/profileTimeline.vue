<template>
<div class="profile-timeline-page">


    <!-- Header -->
    <section class="timeline-header">

        <div>
            <h1>
                Timeline
            </h1>

            <p>
                Your activity history on Scemory
            </p>
        </div>


        <div class="timeline-icon">
            ⏱
        </div>

    </section>



    <!-- Tabs -->

    <div class="timeline-tabs">

        <button
            v-for="tab in tabs"
            :key="tab.key"
            @click="activeTab = tab.key"
            :class="[
                'timeline-tab',
                activeTab === tab.key ? 'active':'' 
            ]"
        >

            <span>
                {{tab.icon}}
            </span>

            {{tab.label}}

        </button>

    </div>




    <!-- Loading -->

    <div
        v-if="loading"
        class="timeline-card empty-state"
    >

        Loading...

    </div>




    <!-- Timeline -->

    <section
        v-else
        class="timeline-card"
    >


        <div
            v-for="item in filteredTimeline"
            :key="item.type + item.created_at"
            class="timeline-row"
        >


            <!-- icon -->

            <div
                class="timeline-marker"
                :class="item.type"
            >

                {{ getIcon(item.type) }}

            </div>




            <!-- Card -->

            <div class="activity-card">


                <div class="activity-head">


                    <div>

                        <h3>
                            {{item.title}}
                        </h3>


                        <span>
                            {{formatDate(item.created_at)}}
                        </span>

                    </div>


                    <div class="arrow">
                        →
                    </div>


                </div>





                <!-- Event -->

                <template v-if="item.type==='event_created'">

                    <div class="event-content">


                        <div class="event-image">

                            <img
                                v-if="item.data.image"
                                :src="item.data.image"
                            />

                            <div v-else>
                                🎫
                            </div>

                        </div>


                        <div>

                            <h4>
                                {{item.data.title}}
                            </h4>

                            <small>
                                Event ID:
                                {{item.data.id}}
                            </small>

                        </div>


                    </div>

                </template>





                <!-- Comment -->

                <template v-else-if="item.type==='comment_created'">

                    <div class="comment-box">

                        {{item.data.comment}}

                    </div>

                </template>






                <!-- Like -->

                <template v-else-if="item.type==='event_liked'">

                    <div class="simple-box">

                        ❤️
                        {{item.data.title}}

                    </div>

                </template>





                <!-- Wishlist -->

                <template v-else-if="item.type==='wishlist_added'">


                    <div class="simple-box">

                        🔖
                        {{item.data.title}}

                    </div>


                </template>






                <template v-else>

                    <div class="simple-box">

                        {{item.data}}

                    </div>

                </template>



            </div>



        </div>



    </section>




</div>
</template>





<script setup>

import {
    ref,
    computed,
    onMounted
}
from "vue";


import {
    profileTimeline
}
from "@/services/profileTimeline/profileTimeline";



const timeline = ref([]);

const loading = ref(false);


const activeTab = ref("all");



const tabs = [

{
    key:"all",
    label:"All",
    icon:"▦"
},

{
    key:"event_created",
    label:"Events",
    icon:"📅"
},

{
    key:"comment_created",
    label:"Comments",
    icon:"💬"
},

{
    key:"event_liked",
    label:"Likes",
    icon:"♡"
},

{
    key:"wishlist_added",
    label:"Wishlist",
    icon:"🔖"
},

{
    key:"purchase",
    label:"Purchases",
    icon:"🛒"
}

];





const filteredTimeline = computed(()=>{


    if(activeTab.value==="all")
        return timeline.value;


    return timeline.value.filter(
        item =>
        item.type === activeTab.value
    );

});







const loadTimeline = async()=>{


loading.value=true;


try{


const response =
await profileTimeline.getTimeline();


timeline.value =
response.data.data || [];


}
catch(error){

console.log(error);

}
finally{

loading.value=false;

}



};








const getIcon=(type)=>{


const icons={

event_created:"📅",

comment_created:"💬",

event_liked:"❤️",

wishlist_added:"🔖",

purchase:"🛒"

};


return icons[type] || "•";


};








const formatDate=(date)=>{


return new Date(date)
.toLocaleDateString(
"en",
{
year:"numeric",
month:"short",
day:"numeric"
}
);


};







onMounted(()=>{

loadTimeline();

});


</script>









<style scoped>


.profile-timeline-page{

max-width:1100px;
margin:120px auto 60px;
padding:0 20px;

}





.timeline-header{


background:white;

border:1px solid #e5edf7;

border-radius:28px;

padding:32px;

display:flex;

justify-content:space-between;

align-items:center;

box-shadow:
0 15px 40px rgba(13,77,151,.08);

}





.timeline-header h1{


font-size:38px;

font-weight:900;

color:#0d4d97;

margin:0;

}





.timeline-header p{


color:#64748b;

margin-top:10px;

}





.timeline-icon{


width:65px;

height:65px;

border-radius:50%;

background:#eaf4ff;

display:flex;

align-items:center;

justify-content:center;

font-size:30px;

}






.timeline-tabs{


margin:30px 0;

background:#fff;

border:1px solid #dbeafe;

border-radius:14px;

padding:5px;

display:flex;

gap:5px;

overflow:auto;

}





.timeline-tab{


border:0;

background:white;

padding:14px 30px;

border-radius:10px;

font-weight:800;

color:#64748b;

cursor:pointer;

display:flex;

gap:8px;

align-items:center;

white-space:nowrap;

}





.timeline-tab.active{


background:#fff;

color:#0d4d97;

box-shadow:
0 5px 20px rgba(13,77,151,.15);

}





.timeline-card{


background:white;

padding:35px;

border-radius:28px;

box-shadow:
0 15px 40px rgba(13,77,151,.08);

}





.timeline-row{


display:flex;

gap:25px;

position:relative;

padding-bottom:35px;

}





.timeline-row:not(:last-child)::before{


content:"";

position:absolute;

left:22px;

top:50px;

height:100%;

width:2px;

background:#dbeafe;

}





.timeline-marker{


width:45px;

height:45px;

border-radius:50%;

background:#0d4d97;

color:white;

display:flex;

align-items:center;

justify-content:center;

z-index:2;

}





.activity-card{


flex:1;

background:#fff;

border:1px solid #edf2f7;

border-radius:22px;

padding:22px;

box-shadow:
0 8px 25px rgba(0,0,0,.04);

}





.activity-head{


display:flex;

justify-content:space-between;

}





.activity-head h3{


margin:0;

color:#0f172a;

font-size:18px;

}





.activity-head span{


color:#94a3b8;

font-size:13px;

}





.arrow{

font-size:25px;

color:#94a3b8;

}






.event-content{


display:flex;

gap:15px;

margin-top:20px;

align-items:center;

}





.event-image{


width:90px;

height:70px;

border-radius:15px;

background:#eaf4ff;

display:flex;

align-items:center;

justify-content:center;

overflow:hidden;

}





.event-image img{

width:100%;
height:100%;
object-fit:cover;

}





.comment-box,
.simple-box{


margin-top:20px;

padding:15px;

border-radius:14px;

background:#f8fafc;

color:#334155;

}




.empty-state{


height:300px;

display:flex;

align-items:center;

justify-content:center;

}





@media(max-width:700px){

.timeline-header{

padding:20px;

}

.timeline-row{

gap:12px;

}

}


</style>