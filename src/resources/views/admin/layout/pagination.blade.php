<nav v-cloak="">
    <ul class="pagination" v-if="data.last_page>1">
        <li class="page-item" @click="getList(1)" v-if="data.current_page>1">
            <a class="page-link">1</a>
        </li>
        <li class="page-item" v-if="data.current_page-3>1"><a class="page-link">…</a></li>
        <li class="page-item" v-if="data.current_page-2>1" @click="getList(data.current_page-2)">
            <a class="page-link">@{{ data.current_page-2 }}</a>
        </li>
        <li class="page-item" v-if="(data.current_page-1)>1" @click="getList(data.current_page-1)">
            <a class="page-link">@{{ data.current_page-1 }}</a>
        </li>
        <li class="page-item active"><a class="page-link">@{{ data.current_page }}</a></li>
        <li class="page-item" v-if="data.current_page+1<data.last_page" @click="getList(data.current_page+1)">
            <a class="page-link">@{{ data.current_page+1 }}</a>
        </li>
        <li class="page-item" v-if="data.current_page+2<data.last_page" @click="getList(data.current_page+2)">
            <a class="page-link">@{{ data.current_page+2 }}</a>
        </li>
        <li class="page-item" v-if="data.current_page+3<data.last_page"><a class="page-link">…</a></li>
        <li class="page-item">
            <a class="page-link" @click="getList(data.last_page)" v-if="data.last_page>data.current_page">
                @{{ data.last_page }}</a>
        </li>
    </ul>
</nav>