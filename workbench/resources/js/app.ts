import "../css/app.css";

import { createInertiaApp } from "@inertiajs/vue3";
import { createApp, h } from "vue";

void createInertiaApp({
  resolve: async (name) => {
    const pages = import.meta.glob("./Pages/**/*.vue");
    const page = pages[`./Pages/${name}.vue`];

    if (!page) {
      throw new Error(`Workbench page [${name}] was not found.`);
    }

    return page();
  },
  setup({ el, App, props, plugin }) {
    createApp({ render: () => h(App, props) }).use(plugin).mount(el);
  },
});
