"use client";

import { FormEvent, useMemo, useState } from "react";
import type { EasyHeadlessForm } from "@/lib/easyheadless";
import { submitForm } from "@/lib/easyheadless";

export function HeadlessForm({ form }: { form: EasyHeadlessForm }) {
  const initialValues = useMemo(
    () =>
      Object.fromEntries(
        form.fields.map((field) => [field.name, ""]),
      ) as Record<string, string>,
    [form.fields],
  );
  const [values, setValues] = useState(initialValues);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [message, setMessage] = useState("");
  const [isSubmitting, setIsSubmitting] = useState(false);

  async function onSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setIsSubmitting(true);
    setErrors({});
    setMessage("");

    const result = await submitForm(form.id, values);

    setIsSubmitting(false);

    if (result.success) {
      setValues(initialValues);
      setMessage(result.confirmation?.message || "Thanks for contacting us.");
      return;
    }

    setErrors(result.errors || { form: result.message || "Unable to submit this form." });
  }

  return (
    <form className="form" onSubmit={onSubmit}>
      {form.fields.map((field) => (
        <label key={field.name}>
          {field.label || field.name}
          {field.type === "textarea" ? (
            <textarea
              name={field.name}
              required={field.required}
              value={values[field.name] || ""}
              onChange={(event) => setValues({ ...values, [field.name]: event.target.value })}
            />
          ) : (
            <input
              name={field.name}
              required={field.required}
              type={field.type === "input_email" ? "email" : "text"}
              value={values[field.name] || ""}
              onChange={(event) => setValues({ ...values, [field.name]: event.target.value })}
            />
          )}
          {errors[field.name] ? <span className="form__error">{errors[field.name]}</span> : null}
        </label>
      ))}
      {errors.form ? <p className="form__error">{errors.form}</p> : null}
      {message ? <p className="form__success">{message}</p> : null}
      <button type="submit" disabled={isSubmitting}>
        {isSubmitting ? "Sending..." : "Send"}
      </button>
    </form>
  );
}
