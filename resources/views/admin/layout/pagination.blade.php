<div v-cloak class="d-flex justify-content-center" v-if="data.last_page > 1">
    <el-pagination
        background
        layout="prev, pager, next"
        :current-page="data.current_page"
        :page-count="data.last_page"
        @current-change="getList"
    ></el-pagination>
</div>
