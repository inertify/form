import {
  createFormRenderer,
  type FormFieldRenderer,
} from "@inertify/form-vue";
import BlocksField from "./BlocksField.vue";
import CheckboxField from "./CheckboxField.vue";
import ChoiceField from "./ChoiceField.vue";
import ColorField from "./ColorField.vue";
import ComposerField from "./ComposerField.vue";
import DateField from "./DateField.vue";
import FileField from "./FileField.vue";
import HiddenField from "./HiddenField.vue";
import KeyValueField from "./KeyValueField.vue";
import LinkField from "./LinkField.vue";
import OtpField from "./OtpField.vue";
import RemoteComboboxField from "./RemoteComboboxField.vue";
import RepeaterField from "./RepeaterField.vue";
import RichTextField from "./RichTextField.vue";
import SliderField from "./SliderField.vue";
import SlugField from "./SlugField.vue";
import TextField from "./TextField.vue";
import TimeField from "./TimeField.vue";
import ToggleField from "./ToggleField.vue";
import UnsupportedField from "./UnsupportedField.vue";

export const formFieldRenderers = {
  Blocks: BlocksField,
  Text: TextField,
  Textarea: {
    component: TextField,
    props: { multiline: true },
  },
  Slug: SlugField,
  Link: LinkField,
  Hidden: HiddenField,
  Otp: OtpField,
  Checkbox: CheckboxField,
  CheckboxGroup: {
    component: ChoiceField,
    props: { multiple: true },
  },
  Radio: ChoiceField,
  Toggle: ToggleField,
  Combobox: RemoteComboboxField,
  DatePicker: DateField,
  TimePicker: TimeField,
  ColorPicker: ColorField,
  Slider: SliderField,
  Repeater: RepeaterField,
  KeyValue: KeyValueField,
  Composer: ComposerField,
  RichText: RichTextField,
  File: FileField,
  Submit: null,
} satisfies Record<string, FormFieldRenderer>;

export const { FormField, FormFields } = createFormRenderer({
  name: "WorkbenchForm",
  renderers: formFieldRenderers,
  fallback: UnsupportedField,
});
